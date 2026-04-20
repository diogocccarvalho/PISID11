import sys
import os
sys.path.append(os.path.join(os.path.dirname(__file__), '..'))

import database.mongo_connect as mongo
import database.mysql_connect as mysql_db
import mysql.connector
import threading
import time

def migrate_collection(collection_name):
    #ligacao
    conn = mysql.connector.connect(**mysql_db.db_config)
    cursor = conn.cursor()

    #Busca o idSimulacao ativo
    cursor.execute("SELECT idSimulacao FROM Simulacao WHERE estado = 'ativo' LIMIT 1")
    resultado = cursor.fetchone()
    if resultado is None:
        print("No active simulation found. Migration aborted.")
        return
    idSimulacao = resultado[0]

    #busca a hora atual
    cursor.execute("SELECT HOUR(NOW())")
    resultado1 = cursor.fetchone()
    hora_atual = resultado1[0]

    documentos = mongo.db[collection_name].find({"migrated": False})
    for d in documentos:
        if collection_name == "Sound":
            data = (d['Sound'], d['Hour'], idSimulacao)
            cursor.execute("INSERT INTO Som (som, hora, idSimulacao) VALUES (%s, %s, %s)", data)
            conn.commit()
        elif collection_name == "Temperature":
            data = (d['Temperature'], d['Hour'], idSimulacao)
            cursor.execute("INSERT INTO Temperatura (temperatura, hora, idSimulacao) VALUES (%s, %s, %s)", data)
            conn.commit()
        elif collection_name == "Motion":
            data = (d['Marsami'], d['RoomOrigin'], d['RoomDestiny'], d['Status'], hora_atual, idSimulacao, d['Player'])
            cursor.execute("INSERT INTO MedicoesPassagem (numeroMarsami, salaOrigem, salaDestino, status, hora, simulacao, equipa) VALUES (%s, %s, %s, %s, %s, %s, %s)", data)
            conn.commit()

        mongo.db[collection_name].update_one({"_id": d["_id"]}, {"$set": {"migrated": True}})

    cursor.close()
    conn.close()

def start_migration():
    while True:
        threads = [
            threading.Thread(target=migrate_collection, args=("Sound",)),
            threading.Thread(target=migrate_collection, args=("Temperature",)),
            threading.Thread(target=migrate_collection, args=("Motion",))
        ]
        for t in threads:
            t.start()
        for t in threads:
            t.join()
        time.sleep(1)

start_migration()