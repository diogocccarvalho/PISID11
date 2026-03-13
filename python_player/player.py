
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

def on_connect(client, userdata, flags, rc):
    print(f"Connected with result code {rc}")
    client.subscribe("pisid_mazesound_13")
    client.subscribe("pisid_mazetemp_13")
    client.subscribe("pisid_mazemov_13")

def on_message(client, userdata, msg):
    data = json.loads(msg.payload)
    if msg.topic == "pisid_mazesound_13":
        db["Sound"].insert_one(data)
        print(f"Sound Sensor: {data['Sound']} dB")
    elif msg.topic == "pisid_mazetemp_13":
        db["Temperature"].insert_one(data)
        print(f"Temperature Sensor: {data['Temperature']} °C")
    elif msg.topic == "pisid_mazemov_13":
        db["Motion"].insert_one(data)
        print(f"Motion Sensor: {data['Marsami']} Marsamis, {data['RoomOrigin']} Quarto de Origem, {data['RoomDestiny']} Quarto de Destino, {data['Status']} Estado")

client = mqtt.Client()
client.on_connect = on_connect
client.on_message = on_message
client.connect("broker.emqx.io", 1883, 60)
client.loop_forever()

