import sys
import os
import json
import paho.mqtt.client as mqtt
import mysql.connector

# Append path to import database modules
sys.path.append(os.path.join(os.path.dirname(__file__), '..'))

import database.mysql_connect as mysql_db

# MQTT -> MySQL
def on_connect(client, userdata, flags, rc):
    print(f"[Computer B] Connected to MQTT Tunnel with code {rc}")
    client.subscribe("pisid_maze_data")

def on_message(client, userdata, msg):
    payload = json.loads(msg.payload)
    collection_name = payload["collection"]
    d = payload["data"]

    try:
        conn = mysql.connector.connect(**mysql_db.db_config)
        cursor = conn.cursor()

        # Get active simulation ID
        cursor.execute("SELECT idSimulacao FROM Simulacao WHERE estado = 'ativo' LIMIT 1")
        resultado = cursor.fetchone()
        if resultado is None:
            print("[Computer B] No active simulation found. Data not inserted.")
            return
        idSimulacao = resultado[0]

        # Get current hour
        cursor.execute("SELECT HOUR(NOW())")
        hora_atual = cursor.fetchone()[0]

        # Insert based on collection type
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

# MAIN do pc 2
if __name__ == "__main__":
    print("[Computer B] Starting MySQL ingestion node...")
    client = mqtt.Client(client_id="Thread3_Subscriber_B")
    client.on_connect = on_connect
    client.on_message = on_message
    
    client.connect("broker.emqx.io", 1883, 60)
    
    try:
        client.loop_forever()
    except KeyboardInterrupt:
        print("Shutting down Computer B node...")