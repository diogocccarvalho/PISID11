import sys
import os
import json
import time
import threading
import paho.mqtt.client as mqtt
import mysql.connector

sys.path.append(os.path.join(os.path.dirname(__file__), '..'))

import database.mongo_connect as mongo
import database.cloud_mysql_connect as cloud

maxtemperature = 0
mintemperature = 0
maxnoise = 0
normaltemperature = 0
normalnoise = 0
warn_high_temp = 0
warn_low_temp = 0
warn_noise = 0

def connect_cloud():
    conn = mysql.connector.connect(**cloud.db_config)
    cursor = conn.cursor()
    cursor.execute("SELECT normaltemperature, temperaturevarhightoleration, temperaturevarlowtoleration, normalnoise, noisevartoleration FROM setupmaze LIMIT 1")
    result = cursor.fetchone()
    global maxtemperature, mintemperature, maxnoise, normaltemperature, normalnoise, warn_high_temp, warn_low_temp, warn_noise
    normaltemperature = float(result[0])
    high_tol          = float(result[1])
    low_tol           = float(result[2])    
    normalnoise       = float(result[3])
    noise_tol         = float(result[4])
    maxtemperature    = normaltemperature + high_tol
    mintemperature    = normaltemperature - low_tol
    maxnoise          = normalnoise + noise_tol

    warn_high_temp    = maxtemperature - (high_tol  * 0.2)
    warn_low_temp     = mintemperature + (low_tol   * 0.2)
    warn_noise        = maxnoise       - (noise_tol * 0.2)
    cursor.close()
    conn.close()

# Servidor -> MongoDB
def server_to_mongo():
    def on_connect(client, userdata, flags, rc):
        print(f"[Thread 1] Connected to Sensor Broker with code {rc}")
        client.subscribe("pisid_mazesound_13")
        client.subscribe("pisid_mazetemp_13")
        client.subscribe("pisid_mazemov_13")

    def on_message(client, userdata, msg):
        data = json.loads(msg.payload)
        data["migrated"] = False
        
        if msg.topic == "pisid_mazesound_13":
            mongo.db["Sound"].insert_one(data)
            print(f"[Thread 1] Sound Sensor: {data['Sound']} dB")
            if data["Sound"] > warn_noise:
                client.publish("pisid_mazeact", "{Type: CloseAllDoor, Player: 13}")
                print("Noise level too high! Closing all doors.")
            elif data["Sound"] <= normalnoise:
                client.publish("pisid_mazeact", "{Type: OpenAllDoor, Player: 13}")
                print("Noise level is normal! Opening all doors.")
        elif msg.topic == "pisid_mazetemp_13":
            mongo.db["Temperature"].insert_one(data)
            print(f"[Thread 2] Temperature Sensor: {data['Temperature']} °C")
            if data["Temperature"] > warn_high_temp or data["Temperature"] < warn_low_temp:
                client.publish("pisid_mazeact", "{Type: AcOn, Player: 13}")
                print("Temperature approaching limit! Turning AC on.")
            elif data["Temperature"] == normaltemperature:
                client.publish("pisid_mazeact", "{Type: AcOff, Player: 13}")
                print("Temperature is normal! Turning AC off.")
        elif msg.topic == "pisid_mazemov_13":
            mongo.db["Motion"].insert_one(data)
            print(f"[Thread 3] Motion Sensor: {data['Marsami']} Marsamis")

    client = mqtt.Client(client_id="Thread1_Sensors_A")
    client.on_connect = on_connect
    client.on_message = on_message
    client.connect("broker.emqx.io", 1883, 60)
    client.loop_forever()

# MongoDB -> MQTT
def mongo_to_mqtt(collection_name, topic, client_id):
    client = mqtt.Client(client_id=client_id)
    client.connect("broker.emqx.io", 1883, 60)
    client.loop_start()

    print(f"[Thread {collection_name}] Started polling MongoDB for unmigrated data...")
    while True:
        documentos = mongo.db[collection_name].find({"migrated": False})
        for d in documentos:
            mongo.db[collection_name].update_one({"_id": d["_id"]}, {"$set": {"migrated": True}})

            if "_id" in d:
                del d["_id"]

            if "migrated" in d:
                del d["migrated"]

            payload = {
                "collection": collection_name,
                "data": d
            }

            client.publish(topic, json.dumps(payload))
                
        time.sleep(1)

# MAIN do pc 1
if __name__ == "__main__":
    connect_cloud()
    print("[Computador 1] A começar...")

    t1 = threading.Thread(target=server_to_mongo, daemon=True)
    
    t2_sound = threading.Thread(target=mongo_to_mqtt, args=("Sound", "pisid_maze_data_sound", "Pub_Sound_A"), daemon=True)
    t3_temp = threading.Thread(target=mongo_to_mqtt, args=("Temperature", "pisid_maze_data_temp", "Pub_Temp_A"), daemon=True)
    t4_motion = threading.Thread(target=mongo_to_mqtt, args=("Motion", "pisid_maze_data_motion", "Pub_Motion_A"), daemon=True)

    t1.start()
    t2_sound.start()
    t3_temp.start()
    t4_motion.start()

    try:
        while True:
            time.sleep(1)
    except KeyboardInterrupt:
        print("Shutting down computer 1...")