
"""
Main Python Player script.
This script will:
1. Connect to MQTT broker
2. Subscribe to topics (pisid_mazesound_n, etc.)
3. Process sensor data
4. Control actuators
"""

import paho.mqtt.client as mqtt

def on_connect(client, userdata, flags, rc):
    print(f"Connected with result code {rc}")
    # client.subscribe("pisid_mazesound_n")

def on_message(client, userdata, msg):
    print(f"{msg.topic} {str(msg.payload)}")

client = mqtt.Client()
client.on_connect = on_connect
client.on_message = on_message

# client.connect("broker.hivemq.com", 1883, 60)
# client.loop_forever()
