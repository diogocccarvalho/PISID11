import sys
import os
import json
import time
import threading
from datetime import datetime
import paho.mqtt.client as mqtt
import mysql.connector

sys.path.append(os.path.join(os.path.dirname(__file__), '..'))

import database.mysql_connect as mysql_db
import database.cloud_mysql_connect as cloud

# Thresholds carregados da cloud
maxtemperature = 0
mintemperature = 0
maxnoise = 0
warn_high_temp = 0
warn_low_temp = 0
warn_noise = 0

# Anti-spam: último alerta enviado por tipo
last_alert_time = {}
ALERT_COOLDOWN = 10  # segundos

def connect_cloud():
    global maxtemperature, mintemperature, maxnoise, warn_high_temp, warn_low_temp, warn_noise
    conn = mysql.connector.connect(**cloud.db_config)
    cursor = conn.cursor()
    cursor.execute("SELECT normaltemperature, temperaturevarhightoleration, temperaturevarlowtoleration, normalnoise, noisevartoleration FROM setupmaze LIMIT 1")
    result = cursor.fetchone()
    cursor.close()
    conn.close()

    normaltemp   = float(result[0])
    high_tol     = float(result[1])
    low_tol      = float(result[2])
    normalnoise  = float(result[3])
    noise_tol    = float(result[4])

    maxtemperature = normaltemp + high_tol
    mintemperature = normaltemp - low_tol
    maxnoise       = normalnoise + noise_tol

    # Aviso a 20% do limite (antes de chegar ao máximo)
    warn_high_temp = maxtemperature - (high_tol * 0.2)
    warn_low_temp  = mintemperature + (low_tol  * 0.2)
    warn_noise     = maxnoise       - (noise_tol * 0.2)

    print(f"[Computer B] Thresholds carregados: temp [{mintemperature:.1f}, {maxtemperature:.1f}] | noise max {maxnoise:.1f}")
    print(f"[Computer B] Avisos a partir de: temp alta {warn_high_temp:.1f} | temp baixa {warn_low_temp:.1f} | ruído {warn_noise:.1f}")

def pode_enviar_alerta(tipo):
    agora = datetime.now()
    ultimo = last_alert_time.get(tipo)
    if ultimo is None or (agora - ultimo).total_seconds() >= ALERT_COOLDOWN:
        last_alert_time[tipo] = agora
        return True
    return False

def inserir_alerta(cursor, sensor, leitura, tipo_alerta, mensagem):
    cursor.execute(
        "INSERT INTO Mensagens (hora, horaEscreita, mensagem, sensor, leitura, tipoALERTA) VALUES (NOW(), NOW(), %s, %s, %s, %s)",
        (mensagem, sensor, leitura, tipo_alerta)
    )
    print(f"[Computer B] ALERTA: {tipo_alerta} | {sensor} = {leitura}")

def process_message(payload):
    collection_name = payload["collection"]
    d = payload["data"]

    try:
        conn = mysql.connector.connect(**mysql_db.db_config)
        cursor = conn.cursor()

        cursor.execute("SELECT idSimulacao FROM Simulacao WHERE estado = 'ativo' LIMIT 1")
        resultado = cursor.fetchone()
        if resultado is None:
            print(f"[Computer B] Sem simulação ativa. {collection_name} não inserido.")
            return
        idSimulacao = resultado[0]

        if collection_name == "Sound":
            valor = d['Sound']
            data = (valor, d['Hour'], idSimulacao)
            cursor.execute("INSERT INTO Som (som, hora, idSimulacao) VALUES (%s, %s, %s)", data)

            if valor > warn_noise and pode_enviar_alerta("SOUND_HIGH"):
                inserir_alerta(cursor, "Sound", valor, "SOUND_HIGH",
                               f"Ruído a aproximar-se do limite máximo ({maxnoise:.1f} dB)")

        elif collection_name == "Temperature":
            valor = d['Temperature']
            data = (valor, d['Hour'], idSimulacao)
            cursor.execute("INSERT INTO Temperatura (temperatura, hora, idSimulacao) VALUES (%s, %s, %s)", data)

            if valor > warn_high_temp and pode_enviar_alerta("TEMP_HIGH"):
                inserir_alerta(cursor, "Temperature", valor, "TEMP_HIGH",
                               f"Temperatura a aproximar-se do limite máximo ({maxtemperature:.1f} °C)")
            elif valor < warn_low_temp and pode_enviar_alerta("TEMP_LOW"):
                inserir_alerta(cursor, "Temperature", valor, "TEMP_LOW",
                               f"Temperatura a aproximar-se do limite mínimo ({mintemperature:.1f} °C)")

        elif collection_name == "Motion":
            data = (d['Marsami'], d['RoomOrigin'], d['RoomDestiny'], d['Status'], idSimulacao, d['Player'])
            cursor.execute("INSERT INTO MedicoesPassagem (numeroMarsami, salaOrigem, salaDestino, status, hora, simulacao, equipa) VALUES (%s, %s, %s, %s, NOW(), %s, %s)", data)

        conn.commit()
        print(f"[Computer B] Inserido {collection_name} no MySQL.")

    except mysql.connector.Error as err:
        print(f"[Computer B] MySQL Error: {err}")
    finally:
        if 'cursor' in locals() and cursor is not None:
            cursor.close()
        if 'conn' in locals() and conn.is_connected():
            conn.close()

# Mqtt -> Sql
def mqtt_subscriber_thread(topic, client_id):
    def on_connect(client, userdata, flags, rc):
        print(f"[Computer B] {client_id} conectado (código {rc}) -> a subscrever: {topic}")
        client.subscribe(topic)

    def on_message(client, userdata, msg):
        payload = json.loads(msg.payload)
        process_message(payload)

    client = mqtt.Client(client_id=client_id)
    client.on_connect = on_connect
    client.on_message = on_message
    client.connect("broker.emqx.io", 1883, 60)
    client.loop_forever()

# MAIN do pc 2
if __name__ == "__main__":
    print("[Computador 2] A começar...")
    connect_cloud()

    t_sound  = threading.Thread(target=mqtt_subscriber_thread, args=("pisid_maze_data_sound",  "Sub_Sound_B"),  daemon=True)
    t_temp   = threading.Thread(target=mqtt_subscriber_thread, args=("pisid_maze_data_temp",   "Sub_Temp_B"),   daemon=True)
    t_motion = threading.Thread(target=mqtt_subscriber_thread, args=("pisid_maze_data_motion", "Sub_Motion_B"), daemon=True)

    t_sound.start()
    t_temp.start()
    t_motion.start()

    try:
        while True:
            time.sleep(1)
    except KeyboardInterrupt:
        print("Shutting down computer 2...")
