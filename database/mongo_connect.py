from pymongo import MongoClient

client_mongo = MongoClient("mongodb://localhost:27017/")
db = client_mongo["pisid"]