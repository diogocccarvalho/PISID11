import sys
import os
import json
import time
import threading
import paho.mqtt.client as mqtt
import mysql.connector

sys.path.append(os.path.join(os.path.dirname(__file__), '..'))

import database.mysql_connect as mysql_db

def process_message(payload):
    collection_name = payload["collection"]
    d = payload["data"]

    print(payload)

    try:
        conn = mysql.connector.connect(**mysql_db.db_config)
        cursor = conn.cursor()

        cursor.execute("SELECT idSimulacao FROM Simulacao WHERE estado = 'ativo' LIMIT 1")
        resultado = cursor.fetchone()
        if resultado is None:
            print(f"[Computer B] No active simulation found. {collection_name} data not inserted.")
            return
        idSimulacao = resultado[0]

        cursor.execute("SELECT HOUR(NOW())")
        hora_atual = cursor.fetchone()[0]

        if collection_name == "Sound":
            data = (d['Sound'], d['Hour'], idSimulacao)
            cursor.execute("INSERT INTO Som (som, hora, idSimulacao) VALUES (%s, %s, %s)", data)
        elif collection_name == "Temperature":
            data = (d['Temperature'], d['Hour'], idSimulacao)
            cursor.execute("INSERT INTO Temperatura (temperatura, hora, idSimulacao) VALUES (%s, %s, %s)", data)
        elif collection_name == "Motion":
            data = (d['Marsami'], d['RoomOrigin'], d['RoomDestiny'], d['Status'], hora_atual, idSimulacao, d['Player'])
            cursor.execute("INSERT INTO MedicoesPassagem (numeroMarsami, salaOrigem, salaDestino, status, hora, simulacao, equipa) VALUES (%s, %s, %s, %s, %s, %s, %s)", data)

        conn.commit()
        print(f"[Computer B] Successfully inserted {collection_name} into MySQL.")

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
        print(f"[Computer B] {client_id} connected to MQTT Tunnel (Code {rc}) -> Subscribing to: {topic}")
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
    
    t_sound = threading.Thread(target=mqtt_subscriber_thread, args=("pisid_maze_data_sound", "Sub_Sound_B"), daemon=True)
    t_temp = threading.Thread(target=mqtt_subscriber_thread, args=("pisid_maze_data_temp", "Sub_Temp_B"), daemon=True)
    t_motion = threading.Thread(target=mqtt_subscriber_thread, args=("pisid_maze_data_motion", "Sub_Motion_B"), daemon=True)

    t_sound.start()
    t_temp.start()
    t_motion.start()
    
    try:
        while True:
            time.sleep(1)
    except KeyboardInterrupt:
        print("Shutting down computer 2...")