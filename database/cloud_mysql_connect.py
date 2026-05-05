import mysql.connector
import os
import configparser

_config = configparser.ConfigParser()
_config.read(os.path.join(os.path.dirname(__file__), '..', 'config.ini'))

_host = _config.get('cloud_mysql', 'host', fallback ='194.210.86.10')
_user = _config.get('cloud_mysql', 'user', fallback  ='aluno')
_password = _config.get('cloud_mysql', 'password', fallback = 'aluno')
_database = _config.get('cloud_mysql', 'database', fallback ='maze')


db_config = {
    "host": _host,
    "user": _user,
    "password": _password,
    "database": _database
}