import json
import time
import threading
import subprocess
from datetime import datetime
import paho.mqtt.client as mqtt
import mysql.connector

GROUP        = 13
MQTT_BROKER  = "broker.emqx.io"
MQTT_PORT    = 1883

CLOUD_DB = {
    "host":     "194.210.86.10",
    "user":     "aluno",
    "password": "aluno",
    "database": "maze",
}

LOCAL_DB = {
    "host":     "localhost",
    "user":     "root",
    "password": "",
    "database": "pisid",
}

TOPIC_MOV     = f"pisid_mazemov_{GROUP}"
TOPIC_CONTROL = f"pisid_game_control_{GROUP}"
TOPIC_ACT     = "pisid_mazeact"

SCORE_INTERVAL    = 4  # segundos entre cada Score enquanto portas fechadas
MAX_TRIGGERS      = 3  # maximo de gatilhos por sala
STRATEGY_INTERVAL = 3  # segundos entre avaliacoes de estrategia

rooms_state      = {}
doors_closed     = False
jogo_ativo       = True
algum_marsami    = False
lock             = threading.Lock()

# mapa de sala -> lista de corridor IDs conectados
room_corridors   = {}  # carregado da cloud no arranque


def carregar_corredores():
    global room_corridors
    try:
        conn   = mysql.connector.connect(**CLOUD_DB)
        cursor = conn.cursor()
        cursor.execute("SELECT room1, room2, corridorId FROM corridor")
        for room1, room2, cid in cursor.fetchall():
            room_corridors.setdefault(room1, []).append(cid)
            room_corridors.setdefault(room2, []).append(cid)
        cursor.close()
        conn.close()
        log(f"Corredores carregados: {len(room_corridors)} salas.")
    except Exception as e:
        log(f"Erro ao carregar corredores: {e}")


def log(msg):
    print(f"[{datetime.now().strftime('%H:%M:%S')}] [Player] {msg}")


def mostrar_pontuacao():
    try:
        conn   = mysql.connector.connect(**CLOUD_DB)
        cursor = conn.cursor()
        cursor.execute(
            "SELECT Room, Score, attempt FROM roomsscore WHERE Player = %s ORDER BY Room",
            (GROUP,)
        )
        rows = cursor.fetchall()
        cursor.close()
        conn.close()

        if not rows:
            log("Sem pontuacao registada na cloud.")
            return

        total = sum(float(r[1]) for r in rows)
        print("\n" + "=" * 45)
        print(f"  PONTUACAO FINAL - Grupo {GROUP}")
        print("=" * 45)
        print(f"  {'Sala':<8} {'Score':<10} {'Tentativas'}")
        print("-" * 45)
        for room, score, attempt in rows:
            if float(score) > 0 or attempt > 0:
                print(f"  {room:<8} {float(score):<10.2f} {attempt}")
        print("-" * 45)
        print(f"  TOTAL:   {total:.2f} pontos")
        print("=" * 45 + "\n")

    except mysql.connector.Error as e:
        log(f"Erro ao ler pontuacao: {e}")


def terminar_simulacao():
    try:
        conn   = mysql.connector.connect(**LOCAL_DB)
        cursor = conn.cursor()
        cursor.execute("SELECT idSimulacao FROM Simulacao WHERE estado = 'ativo' LIMIT 1")
        row = cursor.fetchone()
        if row:
            cursor.callproc("Terminar_simulacao", [row[0]])
            conn.commit()
            log(f"Simulacao {row[0]} terminada.")
        else:
            log("Nenhuma simulacao ativa para terminar.")
        cursor.close()
        conn.close()
    except mysql.connector.Error as e:
        log(f"Erro ao terminar simulacao: {e}")


def mazerun_ativo():
    result = subprocess.run(
        ['tasklist', '/FI', 'IMAGENAME eq mazerun.exe'],
        capture_output=True, text=True
    )
    return 'mazerun.exe' in result.stdout


def matar_mazerun():
    try:
        subprocess.run(['taskkill', '/F', '/IM', 'mazerun.exe'], capture_output=True)
        log("mazerun.exe terminado.")
    except Exception as e:
        log(f"Erro ao matar mazerun: {e}")


def fim_de_jogo():
    global jogo_ativo
    if not jogo_ativo:
        return
    jogo_ativo = False
    log("Fim de jogo detetado.")
    matar_mazerun()
    terminar_simulacao()
    mostrar_pontuacao()


def monitorizar_mysql():
    ultimo_estado = None
    while True:
        time.sleep(3)
        try:
            conn   = mysql.connector.connect(**LOCAL_DB)
            cursor = conn.cursor()
            cursor.execute("SELECT estado FROM Simulacao WHERE estado = 'ativo' LIMIT 1")
            row = cursor.fetchone()
            cursor.close()
            conn.close()

            estado_atual = 'ativo' if row else 'terminada'

            if ultimo_estado == 'ativo' and estado_atual == 'terminada':
                log("Simulacao terminou no MySQL — a encerrar.")
                threading.Thread(target=fim_de_jogo, daemon=True).start()

            ultimo_estado = estado_atual

        except mysql.connector.Error:
            pass


def monitorizar_mazerun():
    # Espera que o mazerun arranque
    log("A aguardar arranque do mazerun...")
    while not mazerun_ativo():
        time.sleep(2)
    log("mazerun detetado a correr.")

    # Agora espera que pare
    while mazerun_ativo():
        time.sleep(2)

    if jogo_ativo:
        log("mazerun parou — fim de jogo detetado.")
        fim_de_jogo()


def publish(client, action_type, **kwargs):
    # formato igual ao computer1.py: {Type: X, Player:13, Key: val}
    parts = [f"Type: {action_type}", f"Player:{GROUP}"]
    for k, v in kwargs.items():
        parts.append(f"{k}: {v}")
    msg = "{" + ", ".join(parts) + "}"
    log(f"-> {msg}")
    client.publish(TOPIC_ACT, msg, qos=2)


def corredores_das_salas(room_ids):
    cids = set()
    for rid in room_ids:
        cids.update(room_corridors.get(rid, []))
    return list(cids)


def fechar_corredores(client, corridor_ids):
    if not corridor_ids:
        publish(client, "CloseAllDoor")
        return
    for cid in corridor_ids:
        publish(client, "CloseDoor", Door=cid)


def abrir_corredores(client, corridor_ids):
    if not corridor_ids:
        publish(client, "OpenAllDoor")
        return
    for cid in corridor_ids:
        publish(client, "OpenDoor", Door=cid)


def esgotar_triggers(client, balanced_rooms, corridor_ids):
    global doors_closed

    max_restantes = max(MAX_TRIGGERS - state["triggers"] for _, state in balanced_rooms)

    for _ in range(max_restantes):
        time.sleep(SCORE_INTERVAL)
        with lock:
            snapshot = {rid: dict(s) for rid, s in rooms_state.items()}

        for rid, _ in balanced_rooms:
            state = snapshot.get(rid, {})
            if state.get("triggers", MAX_TRIGGERS) < MAX_TRIGGERS:
                with lock:
                    rooms_state[rid]["triggers"] += 1
                    used = rooms_state[rid]["triggers"]
                publish(client, "Score", Room=rid)
                log(f"SCORE - sala {rid}  gatilho {used}/{MAX_TRIGGERS} (portas fechadas)")

    abrir_corredores(client, corridor_ids)
    doors_closed = False
    log(f"Corredores {corridor_ids} reabertos - triggers esgotados.")


def strategy_loop(client):
    global doors_closed

    while True:
        time.sleep(STRATEGY_INTERVAL)

        if not jogo_ativo or doors_closed:
            continue

        with lock:
            snapshot = {rid: dict(s) for rid, s in rooms_state.items()}

        total_odd  = sum(s["odd"]  for s in snapshot.values())
        total_even = sum(s["even"] for s in snapshot.values())

        log(f"Estado global - odd={total_odd}  even={total_even}")
        for rid, s in snapshot.items():
            log(f"  sala {rid}: odd={s['odd']} even={s['even']} triggers={s['triggers']}")

        # Todos os marsamis saíram (cansaram-se) -> fim de jogo
        if algum_marsami and total_odd == 0 and total_even == 0:
            threading.Thread(target=fim_de_jogo, daemon=True).start()
            continue

        balanced_rooms = [
            (rid, state)
            for rid, state in snapshot.items()
            if state["odd"] > 0 and state["odd"] == state["even"] and state["triggers"] < MAX_TRIGGERS
        ]

        if balanced_rooms:
            rids = [rid for rid, _ in balanced_rooms]
            corridor_ids = corredores_das_salas(rids)
            fechar_corredores(client, corridor_ids)
            doors_closed = True
            log(f"Corredores fechados: {corridor_ids}")

            for rid, state in balanced_rooms:
                with lock:
                    rooms_state[rid]["triggers"] += 1
                    used = rooms_state[rid]["triggers"]
                publish(client, "Score", Room=rid)
                log(f"SCORE - sala {rid}  (odd={state['odd']}, even={state['even']})  gatilho {used}/{MAX_TRIGGERS}")

            t = threading.Thread(target=esgotar_triggers, args=(client, balanced_rooms, corridor_ids), daemon=True)
            t.start()

        else:
            if total_odd != total_even:
                publish(client, "OpenAllDoor")


def on_connect(client, userdata, flags, rc):
    log(f"Ligado ao broker MQTT (rc={rc})")
    client.subscribe(TOPIC_MOV,     qos=2)
    client.subscribe(TOPIC_CONTROL, qos=2)


def on_message(client, userdata, msg):
    global jogo_ativo, algum_marsami

    if msg.topic == TOPIC_CONTROL:
        raw = msg.payload.decode(errors="ignore")
        print(f"[CONTROLO] {raw}")
        try:
            data = json.loads(raw)
            msg_type = data.get("Type", "")
        except Exception:
            # mazerun envia formato nao-JSON — extrair Type manualmente
            msg_type = ""
            if "GameStart" in raw:
                msg_type = "GameStart"
            elif "GameOver" in raw or "GameEnd" in raw or "GameStop" in raw:
                msg_type = "GameOver"

        if msg_type == "GameStart":
            mostrar_pontuacao()
            with lock:
                rooms_state.clear()
            jogo_ativo    = True
            algum_marsami = False
            log("GameStart - estado reiniciado.")

        elif msg_type in ("GameOver", "GameEnd", "GameStop"):
            threading.Thread(target=fim_de_jogo, daemon=True).start()

        return

    if msg.topic != TOPIC_MOV or not jogo_ativo:
        return

    try:
        data = json.loads(msg.payload)
    except Exception:
        return

    marsami_id  = data.get("Marsami")
    room_origin = data.get("RoomOrigin")
    room_dest   = data.get("RoomDestiny")

    if marsami_id is None or room_dest is None:
        return

    m_type = "even" if marsami_id % 2 == 0 else "odd"

    with lock:
        # RoomOrigin=0 e posicionamento inicial — nao conta para equilibrio
        if room_origin and room_origin != 0:
            if room_origin not in rooms_state:
                rooms_state[room_origin] = {"odd": 0, "even": 0, "triggers": 0}
            if rooms_state[room_origin][m_type] > 0:
                rooms_state[room_origin][m_type] -= 1

        if room_dest != 0 and room_origin != 0:
            if room_dest not in rooms_state:
                rooms_state[room_dest] = {"odd": 0, "even": 0, "triggers": 0}
            rooms_state[room_dest][m_type] += 1
            algum_marsami = True

    log(f"Marsami {marsami_id} ({m_type}) : sala {room_origin} -> sala {room_dest}")


if __name__ == "__main__":
    carregar_corredores()
    client = mqtt.Client(client_id=f"Player_{GROUP}", clean_session=False)
    client.on_connect = on_connect
    client.on_message = on_message
    client.connect(MQTT_BROKER, MQTT_PORT, 60)

    t_strategy = threading.Thread(target=strategy_loop,    args=(client,), daemon=True)
    t_mazerun  = threading.Thread(target=monitorizar_mazerun,              daemon=True)
    t_mysql    = threading.Thread(target=monitorizar_mysql,                daemon=True)
    t_strategy.start()
    t_mazerun.start()
    t_mysql.start()

    log(f"Player ativo - grupo {GROUP}. Ctrl+C para sair.")
    try:
        client.loop_forever()
    except KeyboardInterrupt:
        log("Encerrado.")
        matar_mazerun()
        mostrar_pontuacao()
