

let api = null;
let currentActiveSessionId = null;

function openModal(modalId) {
    const m = document.getElementById(modalId);
    if (m) m.classList.add('active');
}

function closeModal(modalId) {
    const m = document.getElementById(modalId);
    if (m) m.classList.remove('active');
}

function copyJoinCode(code) {
    if (!code) return;
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(code).then(() => {
            handleCopyFeedback(code);
        }).catch(() => {
            fallbackCopy(code);
        });
    } else {
        fallbackCopy(code);
    }
}

function handleCopyFeedback(code) {
    const btn = document.getElementById('btnCopyCode');
    if (btn) {
        const orig = btn.innerHTML;
        btn.innerHTML = '✓ Copied!';
        btn.style.background = '#10B981';
        setTimeout(() => {
            btn.innerHTML = orig;
            btn.style.background = 'rgba(255,255,255,0.18)';
        }, 2200);
    } else {
        alert('Group Join Code copied to clipboard: ' + code);
    }
}

function fallbackCopy(code) {
    const tempInput = document.createElement('input');
    tempInput.value = code;
    document.body.appendChild(tempInput);
    tempInput.select();
    try {
        document.execCommand('copy');
        handleCopyFeedback(code);
    } catch (e) {
        prompt('Copy your Group Join Code:', code);
    }
    document.body.removeChild(tempInput);
}

function openJoinModal(groupId, groupName) {
    const modal = document.getElementById('joinModal');
    const gidInput = document.getElementById('joinGroupId');
    const nameEl = document.getElementById('joinGroupName');
    const codeInput = document.getElementById('joinCodeInput');

    if (gidInput) gidInput.value = groupId;
    if (nameEl) nameEl.textContent = groupName;
    if (codeInput) {
        codeInput.value = '';
        setTimeout(() => codeInput.focus(), 150);
    }
    if (modal) modal.classList.add('active');
}

window.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('active');
    }
});

async function hostStartMeeting(sessionId, title) {
    currentActiveSessionId = sessionId;
    const appEl = document.getElementById('groupApp');
    const groupId = appEl ? appEl.dataset.groupId : '';

    try {
        const formData = new FormData();
        formData.append('action', 'start_meeting');
        formData.append('session_id', sessionId);
        const res = await fetch(`group_view.php?id=${groupId}`, {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            startJitsi(sessionId, title, true);
            const card = document.getElementById('session-card-' + sessionId);
            if (card) card.dataset.status = 'live';
        } else {
            alert(data.message || 'Could not start meeting');
        }
    } catch (err) {
        console.error(err);
        startJitsi(sessionId, title, true);
    }
}

async function hostEndMeeting(sessionId) {
    if (!confirm("Are you sure you want to end this meeting for everyone?")) return;
    const appEl = document.getElementById('groupApp');
    const groupId = appEl ? appEl.dataset.groupId : '';

    try {
        const formData = new FormData();
        formData.append('action', 'end_meeting');
        formData.append('session_id', sessionId);
        const res = await fetch(`group_view.php?id=${groupId}`, {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            closeJitsi();
            location.reload();
        }
    } catch (err) {
        console.error(err);
        location.reload();
    }
}

function hostEndMeetingFromModal() {
    if (currentActiveSessionId) {
        hostEndMeeting(currentActiveSessionId);
    }
}

function startJitsi(roomName, title, isHost = false) {
    currentActiveSessionId = roomName;
    const appEl = document.getElementById('groupApp');
    const currentUser = appEl ? appEl.dataset.userName : 'Student';

    const titleEl = document.getElementById('jitsiModalTitle');
    if (titleEl) {
        titleEl.innerHTML = `
            <span style="display: inline-block; width: 10px; height: 10px; background: #10B981; border-radius: 50%; box-shadow: 0 0 8px #10B981;"></span>
            ${title} ${isHost ? '<span style="font-size:0.75rem; background:#059669; color:white; padding:2px 8px; border-radius:10px; margin-left:8px;">👑 Host</span>' : ''}
        `;
    }

    const jitsiModal = document.getElementById('jitsiModal');
    if (jitsiModal) jitsiModal.classList.add('active');

    if (api) {
        api.dispose();
        api = null;
    }

    const domain = 'meet.jit.si';
    const options = {
        roomName: 'NSBM_StudyCircle_Session_' + roomName,
        width: '100%',
        height: '100%',
        parentNode: document.querySelector('#jitsi-container'),
        userInfo: {
            displayName: currentUser + (isHost ? ' (Host)' : '')
        },
        configOverwrite: {
            startWithAudioMuted: !isHost,
            startWithVideoMuted: false,
            disableDeepLinking: true
        },
        interfaceConfigOverwrite: {
            SHOW_JITSI_WATERMARK: false,
            SHOW_WATERMARK_FOR_GUESTS: false,
            SHOW_BRAND_WATERMARK: false,
            DEFAULT_LOGO_URL: '',
            DEFAULT_BACKGROUND: '#0F172A',
            TOOLBAR_BUTTONS: [
                'microphone', 'camera', 'closedcaptions', 'desktop', 'fullscreen',
                'fodeviceselection', 'hangup', 'profile', 'chat', 'recording',
                'livestreaming', 'etherpad', 'sharedvideo', 'settings', 'raisehand',
                'videoquality', 'filmstrip', 'feedback', 'stats', 'shortcuts',
                'tileview', 'videobackgroundblur', 'download', 'help', 'mute-everyone', 'security'
            ]
        }
    };

    if (typeof JitsiMeetExternalAPI !== 'undefined') {
        api = new JitsiMeetExternalAPI(domain, options);
        api.addEventListeners({
            videoConferenceLeft: function() {
                if (isHost) {
                    const endForAll = confirm("Host Left:\n\nDo you want to END the meeting for everyone?\n\n• Click OK to End for all participants\n• Click Cancel to leave temporarily (meeting remains open)");
                    if (endForAll) {
                        hostEndMeeting(currentActiveSessionId);
                        return;
                    }
                }
                closeJitsi();
            }
        });
    }
}

function closeJitsi() {
    if (api) {
        api.dispose();
        api = null;
    }
    const jitsiModal = document.getElementById('jitsiModal');
    if (jitsiModal) jitsiModal.classList.remove('active');
}

document.addEventListener('DOMContentLoaded', function() {
    const appEl = document.getElementById('groupApp');
    if (!appEl) return;

    const isGroupCreator = appEl.dataset.isCreator === '1';
    const groupId = appEl.dataset.groupId;

    if (!isGroupCreator && groupId) {
        setInterval(async () => {
            try {
                const res = await fetch(`group_view.php?id=${groupId}&api_status=1`);
                const data = await res.json();
                if (data && data.sessions) {
                    let shouldReload = false;
                    data.sessions.forEach(s => {
                        const card = document.getElementById('session-card-' + s.id);
                        if (card && card.dataset.status !== s.status) {
                            shouldReload = true;
                        }
                        if (currentActiveSessionId == s.id && s.status === 'ended' && api) {
                            alert("The host has ended this meeting.");
                            closeJitsi();
                            location.reload();
                        }
                    });
                    if (shouldReload) {
                        location.reload();
                    }
                }
            } catch(e) {}
        }, 3000);
    }
});

