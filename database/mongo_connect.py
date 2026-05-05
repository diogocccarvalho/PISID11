import configparser
import os
from pymongo import MongoClient

_config = configparser.ConfigParser()
_config.read(os.path.join(os.path.dirname(__file__), '..', 'config.ini'))

_host = _config.get('mongo', 'host', fallback='localhost')
_port = _config.getint('mongo', 'port', fallback=27017)
_database = _config.get('mongo', 'database', fallback='pisid')

client_mongo = MongoClient(f"mongodb://{_host}:{_port}/")
db = client_mongo[_database]