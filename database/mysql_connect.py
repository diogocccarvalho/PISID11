import mysql.connector
import os
import configparser

_config = configparser.ConfigParser()
_config.read(os.path.join(os.path.dirname(__file__), '..', 'config.ini'))

_host = _config.get('mysql_local', 'host', fallback = 'localhost')
_user = _config.get('mysql_local', 'user', fallback = 'root')
_password = _config.get('mysql_local', 'password', fallback = '')
_database = _config.get('mysql_local', 'database', fallback = 'pisid')

db_config = {
    "host": _host,
    "user": _user,
    "password": _password,
    "database": _database
}