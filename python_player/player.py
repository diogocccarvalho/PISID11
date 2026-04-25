
"""
Main Python Player script.
This script will:
1. Connect to MQTT broker
2. Subscribe to topics (pisid_mazesound_n, etc.)
3. Process sensor data
4. Control actuators
"""
import sys
import os
sys.path.append(os.path.join(os.path.dirname(__file__), '..'))

import paho.mqtt.client as mqtt
import json
from database.mongo_connect import db
import database.cloud_mysql_connect as cloud
import mysql.connector

maxtemperature = 0
mintemperature = 0
maxnoise = 0
normaltemperature = 0
normalnoise = 0

def connect_cloud():
    conn = mysql.connector.connect(**cloud.db_config)
    cursor = conn.cursor()
    cursor.execute("SELECT normaltemperature, temperaturevarhightoleration, temperaturevarlowtoleration, normalnoise, noisevartoleration FROM setupmaze LIMIT 1")
    result = cursor.fetchone()
    global maxtemperature, mintemperature, maxnoise, normaltemperature, normalnoise
    normaltemperature = result[0]
    maxtemperature = result[0] + result[1]
    mintemperature = result[0] - result[2]
    maxnoise = result[3] + result[4]
    normalnoise = result[3]

def on_connect(client, userdata, flags, rc):
    print(f"Connected with result code {rc}")
    client.subscribe("pisid_mazesound_13")
    client.subscribe("pisid_mazetemp_13")
    client.subscribe("pisid_mazemov_13")

def on_message(client, userdata, msg):
    data = json.loads(msg.payload)
    data["migrated"] = False  # Add migrated flag for MongoDB
    if msg.topic == "pisid_mazesound_13":
        db["Sound"].insert_one(data)
        print(f"Sound Sensor: {data['Sound']} dB")
        if data["Sound"] > maxnoise:
            client.publish("pisid_mazeact", "{Type: CloseAllDoor, Player: 13}")
            print("Noise level too high! Closing all doors.")
        elif data["Sound"] <= normalnoise:
            client.publish("pisid_mazeact", "{Type: OpenAllDoor, Player: 13}")
            print("Noise level is normal! Opening all doors.")
    elif msg.topic == "pisid_mazetemp_13":
        db["Temperature"].insert_one(data)
        print(f"Temperature Sensor: {data['Temperature']} °C")
        if data["Temperature"] > maxtemperature:
            client.publish("pisid_mazeact", "{Type: AcOn, Player: 13}")
            print("Temperature too high! Turning AC on.")
        elif data["Temperature"] < mintemperature:
            client.publish("pisid_mazeact", "{Type: AcOn, Player: 13}")
            print("Temperature too low! Turning AC on.")
        elif data["Temperature"] == normaltemperature:
            client.publish("pisid_mazeact", "{Type: AcOff, Player: 13}")
            print("Temperature is normal! Turning AC off.")
    elif msg.topic == "pisid_mazemov_13":
        db["Motion"].insert_one(data)
        print(f"Motion Sensor: {data['Marsami']} Marsamis, {data['RoomOrigin']} Quarto de Origem, {data['RoomDestiny']} Quarto de Destino, {data['Status']} Estado")


connect_cloud()
client = mqtt.Client()
client.on_connect = on_connect
client.on_message = on_message
client.connect("broker.emqx.io", 1883, 60)
client.loop_forever()

