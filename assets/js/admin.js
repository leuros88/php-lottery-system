// Admin JavaScript
(function() {
    'use strict';

    // Show modals on load if present
    var modals = ['auto-assign-modal', 'result-modal'];
    for (var i = 0; i < modals.length; i++) {
        var m = document.getElementById(modals[i]);
        if (m) {
            m.style.display = 'flex';
            break;
        }
    }
})();

function closeModal() {
    var ids = ['auto-assign-modal', 'result-modal'];
    for (var i = 0; i < ids.length; i++) {
        var m = document.getElementById(ids[i]);
        if (m) {
            m.style.display = 'none';
        }
    }
}

function copyModalMessage() {
    var el = document.getElementById('modal-message');
    if (!el) return;
    var text = el.innerText.trim();

    function done() {
        var btn = document.querySelector('.modal-footer .btn-secondary');
        if (btn) { btn.textContent = '✅ Copied!'; setTimeout(function () { btn.innerHTML = '📋 Copy'; }, 2000); }
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done).catch(done);
    } else {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch(e) {}
        document.body.removeChild(ta);
        done();
    }
}

function randomNumber(targetId, btn) {
    var input = document.getElementById(targetId);
    if (!input) return;

    btn.disabled = true;
    btn.textContent = '...';

    var xhr = new XMLHttpRequest();
    // Relative URL: works whatever the admin folder is renamed to (see ADMIN_DIR).
    xhr.open('GET', 'ajax.php?action=random_number', true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var response = JSON.parse(xhr.responseText);
                if (response.number) {
                    input.value = response.number;
                }
            } catch(e) {}
        }
        btn.disabled = false;
        btn.innerHTML = '&#9852;';
    };
    xhr.onerror = function() {
        btn.disabled = false;
        btn.innerHTML = '&#9852;';
    };
    xhr.send();
}
