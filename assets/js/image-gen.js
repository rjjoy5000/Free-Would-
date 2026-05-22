/* ========================================
   Free Would - Image Generation JavaScript
   ======================================== */

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('image-gen-form');
    if (!form) return;

    const promptInput = document.getElementById('prompt');
    const negativePrompt = document.getElementById('negative-prompt');
    const providerSelect = document.getElementById('provider');
    const modelSelect = document.getElementById('model');
    const sizeSelect = document.getElementById('size');
    const styleSelect = document.getElementById('style');
    const qualitySelect = document.getElementById('quality');
    const generateBtn = document.getElementById('generate-btn');
    const gallery = document.getElementById('image-gallery');
    const loading = document.getElementById('loading');

    const providerModels = {
        openai: ['dall-e-3', 'dall-e-2'],
        stability: ['stable-diffusion-xl-1024-v1-0', 'stable-diffusion-xl-1024-v0-9'],
        replicate: ['stability-ai/sdxl', 'black-forest-labs/flux-schnell'],
        huggingface: ['stabilityai/stable-diffusion-xl-base-1.0']
    };

    if (providerSelect) {
        providerSelect.addEventListener('change', () => {
            const provider = providerSelect.value;
            if (modelSelect && providerModels[provider]) {
                modelSelect.innerHTML = providerModels[provider].map(m =>
                    `<option value="${m}">${m}</option>`
                ).join('');
            }
        });
        providerSelect.dispatchEvent(new Event('change'));
    }

    const negToggle = document.getElementById('neg-prompt-toggle');
    if (negToggle && negativePrompt) {
        negToggle.addEventListener('click', () => {
            negativePrompt.parentElement.style.display =
                negativePrompt.parentElement.style.display === 'none' ? 'block' : 'none';
        });
    }

    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const token = localStorage.getItem('fw_token');
            if (!token) { window.location.href = 'login.html'; return; }

            const prompt = promptInput.value.trim();
            if (!prompt) { showToast('Please enter a prompt', 'error'); return; }

            generateBtn.disabled = true;
            generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
            if (loading) loading.style.display = 'flex';

            try {
                const res = await fetch('backend/image-gen.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + token
                    },
                    body: JSON.stringify({
                        action: 'generate',
                        prompt: prompt,
                        negative_prompt: negativePrompt ? negativePrompt.value : '',
                        provider: providerSelect ? providerSelect.value : 'openai',
                        model: modelSelect ? modelSelect.value : 'dall-e-3',
                        size: sizeSelect ? sizeSelect.value : '1024x1024',
                        style: styleSelect ? styleSelect.value : 'vivid',
                        quality: qualitySelect ? qualitySelect.value : 'standard'
                    })
                });

                const data = await res.json();

                if (data.success) {
                    addImageToGallery(data);
                    showToast('Image generated successfully!');
                    const creditsEl = document.querySelector('.user-credits');
                    if (creditsEl && data.credits_remaining !== undefined) {
                        creditsEl.textContent = data.credits_remaining;
                    }
                } else {
                    showToast(data.message || 'Generation failed', 'error');
                }
            } catch (err) {
                showToast('Network error. Please try again.', 'error');
                console.error(err);
            } finally {
                generateBtn.disabled = false;
                generateBtn.innerHTML = '<i class="fas fa-wand-magic-sparkles"></i> Generate Image (1 Credit)';
                if (loading) loading.style.display = 'none';
            }
        });
    }

    async function loadHistory() {
        const token = localStorage.getItem('fw_token');
        try {
            const res = await fetch('backend/image-gen.php?action=history', {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            const data = await res.json();
            if (data.success && data.images) {
                data.images.forEach(img => addImageToGallery(img));
            }
        } catch (err) {
            console.error('Failed to load history:', err);
        }
    }

    function addImageToGallery(data) {
        if (!gallery) return;
        const empty = gallery.querySelector('.empty-state');
        if (empty) empty.remove();

        const item = document.createElement('div');
        item.className = 'gallery-item';
        item.innerHTML = `
            <img src="${escapeHtml(data.image_url)}" alt="Generated image" loading="lazy">
            <div class="gallery-item-info">
                <p class="prompt">${escapeHtml(data.prompt || '')}</p>
                <div class="gallery-item-actions">
                    <button onclick="downloadImage('${escapeHtml(data.image_url)}')" title="Download"><i class="fas fa-download"></i></button>
                    <button onclick="copyText('${escapeHtml(data.prompt || '')}')" title="Copy Prompt"><i class="fas fa-copy"></i></button>
                    <button onclick="deleteImage(${data.id || 0}, this)" title="Delete"><i class="fas fa-trash"></i></button>
                </div>
            </div>
        `;
        gallery.prepend(item);
    }

    loadHistory();
});

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

async function downloadImage(url) {
    try {
        const res = await fetch(url);
        const blob = await res.blob();
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'freewould-image-' + Date.now() + '.png';
        a.click();
        URL.revokeObjectURL(a.href);
    } catch (err) {
        window.open(url, '_blank');
    }
}

function copyText(text) {
    navigator.clipboard.writeText(text).then(() => showToast('Copied!')).catch(() => {});
}

async function deleteImage(id, btn) {
    if (!id || !confirm('Delete this image?')) return;
    const token = localStorage.getItem('fw_token');
    try {
        const res = await fetch('backend/image-gen.php', {
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
            showToast('Image deleted');
        }
    } catch (err) {
        showToast('Failed to delete', 'error');
    }
}
