/* ========================================
   Free Would - Video Generation JavaScript
   ======================================== */

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('video-gen-form');
    if (!form) return;

    const promptInput = document.getElementById('prompt');
    const providerSelect = document.getElementById('provider');
    const durationSelect = document.getElementById('duration');
    const resolutionSelect = document.getElementById('resolution');
    const fpsSelect = document.getElementById('fps');
    const generateBtn = document.getElementById('generate-btn');
    const videoPreview = document.getElementById('video-preview');
    const statusIndicator = document.getElementById('status-indicator');
    const videoHistory = document.getElementById('video-history');

    let pollingInterval = null;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const token = localStorage.getItem('fw_token');
        if (!token) { window.location.href = 'login.html'; return; }

        const prompt = promptInput.value.trim();
        if (!prompt) { showToast('Please enter a prompt', 'error'); return; }

        generateBtn.disabled = true;
        generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
        updateStatus('processing', 'Processing your request...');

        try {
            const res = await fetch('backend/video-gen.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + token
                },
                body: JSON.stringify({
                    action: 'generate',
                    prompt: prompt,
                    provider: providerSelect ? providerSelect.value : 'runway',
                    duration: durationSelect ? parseInt(durationSelect.value) : 5,
                    resolution: resolutionSelect ? resolutionSelect.value : '720p',
                    fps: fpsSelect ? parseInt(fpsSelect.value) : 24
                })
            });

            const data = await res.json();

            if (data.success) {
                showToast('Video generation started!');
                if (data.video_id) {
                    startPolling(data.video_id);
                }
                const creditsEl = document.querySelector('.user-credits');
                if (creditsEl && data.credits_remaining !== undefined) {
                    creditsEl.textContent = data.credits_remaining;
                }
            } else {
                showToast(data.message || 'Generation failed', 'error');
                updateStatus('failed', 'Generation failed');
            }
        } catch (err) {
            showToast('Network error. Please try again.', 'error');
            updateStatus('failed', 'Network error');
        } finally {
            generateBtn.disabled = false;
            generateBtn.innerHTML = '<i class="fas fa-film"></i> Generate Video (10 Credits)';
        }
    });

    function startPolling(videoId) {
        if (pollingInterval) clearInterval(pollingInterval);
        pollingInterval = setInterval(async () => {
            const token = localStorage.getItem('fw_token');
            try {
                const res = await fetch(`backend/video-gen.php?action=status&id=${videoId}`, {
                    headers: { 'Authorization': 'Bearer ' + token }
                });
                const data = await res.json();

                if (data.success) {
                    updateStatus(data.status, getStatusMessage(data.status));

                    if (data.status === 'completed' && data.video_url) {
                        clearInterval(pollingInterval);
                        showVideoPreview(data.video_url);
                        addVideoToHistory(data);
                        showToast('Video generated successfully!');
                    } else if (data.status === 'failed') {
                        clearInterval(pollingInterval);
                        showToast('Video generation failed', 'error');
                    }
                }
            } catch (err) {
                console.error('Polling error:', err);
            }
        }, 3000);
    }

    function updateStatus(status, message) {
        if (!statusIndicator) return;
        statusIndicator.className = 'status-indicator ' + status;
        statusIndicator.innerHTML = `
            <span class="status-dot ${status === 'completed' ? 'active' : status === 'failed' ? 'failed' : 'pending'}"></span>
            <span>${message}</span>
            ${status === 'processing' ? '<div class="spinner" style="width:20px;height:20px;border-width:2px;"></div>' : ''}
        `;
    }

    function getStatusMessage(status) {
        const messages = {
            pending: 'Waiting in queue...',
            processing: 'Generating your video...',
            completed: 'Video ready!',
            failed: 'Generation failed'
        };
        return messages[status] || status;
    }

    function showVideoPreview(url) {
        if (!videoPreview) return;
        videoPreview.innerHTML = `
            <div class="video-player">
                <video controls autoplay>
                    <source src="${escapeHtml(url)}" type="video/mp4">
                </video>
            </div>
        `;
    }

    function addVideoToHistory(data) {
        if (!videoHistory) return;
        const empty = videoHistory.querySelector('.empty-state');
        if (empty) empty.remove();

        const item = document.createElement('div');
        item.className = 'gallery-item';
        item.innerHTML = `
            <div class="video-player" style="aspect-ratio:16/9;">
                <video muted preload="metadata">
                    <source src="${escapeHtml(data.video_url)}" type="video/mp4">
                </video>
            </div>
            <div class="gallery-item-info">
                <p class="prompt">${escapeHtml(data.prompt || '')}</p>
                <div class="gallery-item-actions">
                    <button onclick="window.open('${escapeHtml(data.video_url)}', '_blank')"><i class="fas fa-play"></i> Play</button>
                    <button onclick="downloadVideo('${escapeHtml(data.video_url)}')"><i class="fas fa-download"></i></button>
                    <button onclick="deleteVideo(${data.id || 0}, this)"><i class="fas fa-trash"></i></button>
                </div>
            </div>
        `;
        videoHistory.prepend(item);
    }

    async function loadHistory() {
        const token = localStorage.getItem('fw_token');
        try {
            const res = await fetch('backend/video-gen.php?action=history', {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            const data = await res.json();
            if (data.success && data.videos) {
                data.videos.forEach(v => addVideoToHistory(v));
            }
        } catch (err) {
            console.error('Failed to load history:', err);
        }
    }

    loadHistory();
});

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

async function downloadVideo(url) {
    try {
        const res = await fetch(url);
        const blob = await res.blob();
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'freewould-video-' + Date.now() + '.mp4';
        a.click();
        URL.revokeObjectURL(a.href);
    } catch (err) {
        window.open(url, '_blank');
    }
}

async function deleteVideo(id, btn) {
    if (!id || !confirm('Delete this video?')) return;
    const token = localStorage.getItem('fw_token');
    try {
        const res = await fetch('backend/video-gen.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + token
            },
            body: JSON.stringify({ action: 'delete', id: id })
        });
        const data = await res.json();
        if (data.success) {
            btn.closest('.gallery-item').remove();
            showToast('Video deleted');
        }
    } catch (err) {
        showToast('Failed to delete', 'error');
    }
}
