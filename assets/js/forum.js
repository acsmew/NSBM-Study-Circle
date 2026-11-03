

function showToast(msg) {
    let toast = document.getElementById('toastNotice');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'toastNotice';
        document.body.appendChild(toast);
    }
    toast.textContent = msg;
    toast.style.display = 'block';
    setTimeout(() => { toast.style.display = 'none'; }, 3000);
}

function openPostModal(focusType) {
    const modal = document.getElementById('askModal');
    if (modal) {
        modal.classList.add('active');
    }
    if (focusType === 'media') {
        setTimeout(() => {
            const fileInput = document.getElementById('mediaFileInput');
            if (fileInput) fileInput.click();
        }, 100);
    }
}

function closePostModal() {
    const modal = document.getElementById('askModal');
    if (modal) {
        modal.classList.remove('active');
    }
}

window.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('active');
    }
});

function previewMediaUpload(input) {
    const previewBox = document.getElementById('mediaPreviewBox');
    const imgPreview = document.getElementById('imgPreview');
    const vidPreview = document.getElementById('vidPreview');

    if (input.files && input.files[0]) {
        const file = input.files[0];
        const fileType = file.type;
        const fileURL = URL.createObjectURL(file);

        if (previewBox) previewBox.style.display = 'block';
        if (fileType.startsWith('image/')) {
            if (imgPreview) {
                imgPreview.src = fileURL;
                imgPreview.style.display = 'block';
            }
            if (vidPreview) vidPreview.style.display = 'none';
        } else if (fileType.startsWith('video/')) {
            if (vidPreview) {
                vidPreview.src = fileURL;
                vidPreview.style.display = 'block';
            }
            if (imgPreview) imgPreview.style.display = 'none';
        }
    }
}

function clearMediaUpload() {
    const fi = document.getElementById('mediaFileInput');
    const ip = document.getElementById('imgPreview');
    const vp = document.getElementById('vidPreview');
    const mb = document.getElementById('mediaPreviewBox');
    if (fi) fi.value = '';
    if (ip) ip.src = '';
    if (vp) vp.src = '';
    if (mb) mb.style.display = 'none';
}

function openLightbox(url) {
    const lim = document.getElementById('mediaLightboxImg');
    const lm = document.getElementById('mediaLightboxModal');
    if (lim) lim.src = url;
    if (lm) lm.classList.add('active');
}

function closeLightbox() {
    const lm = document.getElementById('mediaLightboxModal');
    if (lm) lm.classList.remove('active');
}

function toggleComments(postId) {
    const drawer = document.getElementById('comments-drawer-' + postId);
    if (drawer) {
        const isHidden = window.getComputedStyle(drawer).display === 'none';
        drawer.style.display = isHidden ? 'block' : 'none';
        if (isHidden) {
            const input = document.getElementById('comment-input-' + postId);
            if (input) input.focus();
        }
    }
}

function sharePostLink(postId) {
    const postUrl = window.location.origin + window.location.pathname.replace('forum.php', 'forum_view.php') + '?id=' + postId;
    if (navigator.clipboard) {
        navigator.clipboard.writeText(postUrl).then(() => {
            showToast('Link copied to clipboard! 📋');
        });
    } else {
        prompt('Copy link to post:', postUrl);
    }
}

async function sendReaction(arg1, arg2) {
    try {
        document.querySelectorAll('.reaction-flyout').forEach(f => f.classList.remove('is-open'));

        let postId;
        let reactionType;

        if (arg2 !== undefined) {

            postId = arg1;
            reactionType = arg2;
        } else {

            const urlParams = new URLSearchParams(window.location.search);
            postId = urlParams.get('id');
            reactionType = arg1;
        }

        const isDetailView = !!document.getElementById('detail-react-btn');
        const endpoint = isDetailView ? `forum_view.php?id=${postId}` : 'forum.php';

        const formData = new FormData();
        formData.append('action', 'react');
        if (!isDetailView) formData.append('post_id', postId);
        formData.append('reaction_type', reactionType);

        const res = await fetch(endpoint, {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            const icons = { like: '👍', love: '❤️', haha: '😆', wow: '😮', sad: '😢' };
            const labels = { like: 'Liked', love: 'Love', haha: 'Haha', wow: 'Wow', sad: 'Sad' };

            if (isDetailView) {

                const statEl = document.getElementById('detail-stat-reactions');
                if (statEl) statEl.textContent = data.total + ' reactions';

                const btn = document.getElementById('detail-react-btn');
                const iconSpan = document.getElementById('detail-react-icon');
                const labelSpan = document.getElementById('detail-react-label');

                if (btn && iconSpan && labelSpan) {
                    btn.className = 'action-button-btn';
                    if (data.current_reaction) {
                        iconSpan.textContent = icons[data.current_reaction];
                        labelSpan.textContent = labels[data.current_reaction];
                        btn.classList.add('reacted-' + data.current_reaction);
                    } else {
                        iconSpan.textContent = '👍';
                        labelSpan.textContent = 'Like';
                    }
                }
            } else {

                const statEl = document.getElementById('stat-total-' + postId);
                if (statEl) statEl.textContent = data.total + ' reactions';

                const btn = document.getElementById('react-btn-' + postId);
                const iconSpan = document.getElementById('react-icon-' + postId);
                const labelSpan = document.getElementById('react-label-' + postId);

                if (btn && iconSpan && labelSpan) {
                    btn.className = 'action-button-btn';
                    if (data.current_reaction) {
                        iconSpan.textContent = icons[data.current_reaction];
                        labelSpan.textContent = labels[data.current_reaction];
                        btn.classList.add('reacted-' + data.current_reaction);
                    } else {
                        iconSpan.textContent = '👍';
                        labelSpan.textContent = 'Like';
                    }
                }
            }
        }
    } catch(e) {
        console.error(e);
    }
}

function toggleDefaultReaction(postId) {
    sendReaction(postId, 'like');
}

async function submitCommentAjax(e, postId) {
    e.preventDefault();
    const input = document.getElementById('comment-input-' + postId);
    const content = input.value.trim();
    if (!content) return;

    try {
        const formData = new FormData();
        formData.append('action', 'new_reply');
        formData.append('post_id', postId);
        formData.append('content', content);
        formData.append('ajax', '1');

        const res = await fetch('forum.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.success && data.reply) {
            input.value = '';
            const r = data.reply;
            const list = document.getElementById('comments-list-' + postId);

            const item = document.createElement('div');
            item.className = 'comment-item';
            item.innerHTML = `
                <div class="comment-avatar">${r.avatar_letter}</div>
                <div style="flex: 1;">
                    <div class="comment-bubble">
                        <div class="comment-author-name">
                            ${r.author_name}
                            <span class="badge-batch comment-badge">${r.author_batch}</span>
                            <span class="badge-faculty comment-badge">${r.author_faculty}</span>
                        </div>
                        <div class="comment-text">${r.content}</div>
                    </div>
                    <div class="comment-time">${r.created_at}</div>
                </div>
            `;
            list.appendChild(item);

            const currentCount = list.children.length;
            const statCom = document.getElementById('stat-comments-' + postId);
            if (statCom) statCom.textContent = currentCount + ' comments';
            showToast('Comment posted! 💬');
        } else {
            alert(data.message || 'Could not post comment.');
        }
    } catch(err) {
        console.error(err);
    }
}
