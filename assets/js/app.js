document.addEventListener('DOMContentLoaded', () => {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

  const msgNetwork =
    "We couldn't reach the server. Check your connection and try again.";
  const msgBadJson = 'Something went wrong on our end. Refresh the page and try again.';

  async function fetchJson(url, init) {
    let response;
    try {
      response = await fetch(url, init);
    } catch {
      return { ok: false, reason: 'network' };
    }
    try {
      return { ok: true, data: await response.json() };
    } catch {
      return { ok: false, reason: 'json' };
    }
  }

  const navAccount = document.querySelector('[data-nav-account]');
  if (navAccount) {
    const toggle = navAccount.querySelector('.site-nav__account-toggle');
    const menu = navAccount.querySelector('.site-nav__account-menu');
    function setOpen(open) {
      navAccount.classList.toggle('is-open', open);
      if (menu) menu.hidden = !open;
      if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    toggle?.addEventListener('click', (e) => {
      e.stopPropagation();
      setOpen(menu?.hidden !== false);
    });
    document.addEventListener('click', (e) => {
      if (!navAccount.contains(e.target)) setOpen(false);
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && navAccount.classList.contains('is-open')) {
        setOpen(false);
        toggle?.focus();
      }
    });
  }

  const editorForm = document.getElementById('editor-form');
  const imageInput = document.getElementById('image-input');
  const previewImage = document.getElementById('preview-image');
  const overlayInput = document.getElementById('overlay-input');
  const editorPreviewHost = document.getElementById('editor-preview-host');
  const resultImage = document.getElementById('result-image');
  const resultSection = document.getElementById('result-section');
  const resultActions = document.getElementById('result-actions');
  const discardPendingBtn = document.getElementById('discard-pending-btn');
  const publishPendingBtn = document.getElementById('publish-pending-btn');
  const overlayButtons = document.querySelectorAll('.overlay-option');

  const video = document.getElementById('webcam-video');
  const canvas = document.getElementById('webcam-canvas');
  const overlayPreviewCanvas = document.getElementById('webcam-overlay-canvas');
  const webcamComposite = document.getElementById('webcam-composite');
  const startBtn = document.getElementById('webcam-start');
  const stopBtn = document.getElementById('webcam-stop');
  const captureBtn = document.getElementById('webcam-capture');
  const tabButtons = document.querySelectorAll('.editor-tab');
  const panelWebcam = document.getElementById('panel-webcam');
  const panelUpload = document.getElementById('panel-upload');

  let mediaStream = null;
  let capturedBlob = null;

  let createLocked = false;

  function hasBaseImageSource() {
    const file = imageInput?.files?.[0];
    return !!(file && file.size > 0) || !!capturedBlob;
  }

  function updateCaptureButtonState() {
    if (!captureBtn || !video) return;
    const hasDims = !!(video.videoWidth > 0 && video.videoHeight > 0);
    const hasOverlay = !!(overlayInput?.value || '').trim();
    captureBtn.disabled = !(
      mediaStream &&
      !video.classList.contains('hidden') &&
      hasDims &&
      hasOverlay
    );
  }

  function loadImageFromUrl(url) {
    return new Promise((resolve, reject) => {
      const img = new Image();
      img.onload = () => resolve(img);
      img.onerror = () => reject(new Error('Could not load that image.'));
      img.src = url;
    });
  }

  const EDITOR_MAX_IMAGE_EDGE = 2048;

  async function prepareBaseBlobForCommit(blobOrFile) {
    const url = URL.createObjectURL(blobOrFile);
    try {
      const baseIm = await loadImageFromUrl(url);
      let w = baseIm.naturalWidth;
      let h = baseIm.naturalHeight;
      if (!w || !h) throw new Error('Invalid image size');
      if (w <= EDITOR_MAX_IMAGE_EDGE && h <= EDITOR_MAX_IMAGE_EDGE) {
        return blobOrFile;
      }
      const scale = EDITOR_MAX_IMAGE_EDGE / Math.max(w, h);
      w = Math.round(w * scale);
      h = Math.round(h * scale);
      const cx = document.createElement('canvas');
      cx.width = w;
      cx.height = h;
      const ctx = cx.getContext('2d');
      if (!ctx) throw new Error('No canvas context');
      ctx.drawImage(baseIm, 0, 0, w, h);
      return await new Promise((resolve, reject) => {
        cx.toBlob(
          (b) => (b ? resolve(b) : reject(new Error('Could not resize image'))),
          'image/jpeg',
          0.88
        );
      });
    } finally {
      URL.revokeObjectURL(url);
    }
  }

  async function captureWebcamFrameBlob() {
    const w = video.videoWidth;
    const h = video.videoHeight;
    if (!w || !h) throw new Error('Camera not ready');
    canvas.width = w;
    canvas.height = h;
    const ctx = canvas.getContext('2d');
    if (!ctx) throw new Error('No canvas');
    ctx.drawImage(video, 0, 0, w, h);
    return await new Promise((resolve, reject) => {
      canvas.toBlob(
        (b) => (b ? resolve(b) : reject(new Error('Could not capture from the camera.'))),
        'image/png',
        0.92
      );
    });
  }

  async function commitEditorComposite(originalBlob) {
    const fd = new FormData();
    fd.append('csrf_token', csrf);
    fd.append('overlay', overlayInput.value);
    const origName =
      originalBlob instanceof File
        ? originalBlob.name
        : originalBlob.type === 'image/jpeg'
          ? 'photo.jpg'
          : 'capture.png';
    fd.append('original', originalBlob, origName);

    const result = await fetchJson('api/commit_editor_composite.php', {
      method: 'POST',
      body: fd
    });
    if (!result.ok) {
      alert(result.reason === 'network' ? msgNetwork : msgBadJson);
      return false;
    }
    const { data } = result;
    if (!data.success) {
      alert(data.error || 'We could not save your image.');
      return false;
    }

    if (resultImage) {
      resultImage.src = `${data.final_path}?t=${Date.now()}`;
    }
    if (resultSection) resultSection.classList.remove('hidden');
    if (resultActions) resultActions.classList.remove('hidden');
    if (editorPreviewHost) editorPreviewHost.classList.add('hidden');
    if (previewImage) previewImage.classList.add('hidden');

    createLocked = true;
    return true;
  }

  async function recommitFromStoredBase() {
    if (!overlayInput?.value?.trim()) return;
    let rawBase = null;
    if (capturedBlob) {
      rawBase = capturedBlob;
    } else if (imageInput?.files?.[0]) {
      rawBase = imageInput.files[0];
    } else {
      return;
    }
    try {
      const base = await prepareBaseBlobForCommit(rawBase);
      await commitEditorComposite(base);
    } catch (e) {
      alert(e.message || 'Could not update your photo.');
    }
  }

  function overlayPreviewSrc(filename) {
    const path = `overlays/${encodeURIComponent(filename)}`;
    try {
      return new URL(path, window.location.href).href;
    } catch {
      return path;
    }
  }

  let liveOverlayRaf = 0;
  let liveOverlayImg = null;
  let liveOverlayUrl = '';

  function cancelLiveOverlayRaf() {
    if (liveOverlayRaf) {
      cancelAnimationFrame(liveOverlayRaf);
      liveOverlayRaf = 0;
    }
  }

  function hideLiveOverlayCanvas() {
    cancelLiveOverlayRaf();
    if (webcamComposite) webcamComposite.classList.remove('is-live-composite');
    if (!overlayPreviewCanvas) return;
    overlayPreviewCanvas.hidden = true;
    overlayPreviewCanvas.classList.add('hidden');
    overlayPreviewCanvas.setAttribute('aria-hidden', 'true');
    const c = overlayPreviewCanvas.getContext('2d');
    if (c && overlayPreviewCanvas.width > 0) {
      c.clearRect(0, 0, overlayPreviewCanvas.width, overlayPreviewCanvas.height);
    }
  }

  function drawVideoLetterbox(ctx, vid, boxW, boxH) {
    const iw = vid.videoWidth;
    const ih = vid.videoHeight;
    if (!iw || !ih) return null;
    const scale = Math.min(boxW / iw, boxH / ih);
    const dw = iw * scale;
    const dh = ih * scale;
    const dx = (boxW - dw) / 2;
    const dy = (boxH - dh) / 2;
    ctx.fillStyle = '#111';
    ctx.fillRect(0, 0, boxW, boxH);
    ctx.drawImage(vid, 0, 0, iw, ih, dx, dy, dw, dh);
    return { dx, dy, dw, dh };
  }

  function liveCompositeShouldRun() {
    if (!video || !overlayInput || !panelWebcam || !overlayPreviewCanvas) return false;
    const name = (overlayInput.value || '').trim();
    return !!(
      name &&
      mediaStream &&
      !video.classList.contains('hidden') &&
      !panelWebcam.hidden
    );
  }

  function drawLiveCompositeFrame() {
    if (!overlayPreviewCanvas || !video) return;
    const boxW = video.clientWidth;
    const boxH = video.clientHeight;
    if (boxW < 2 || boxH < 2) return;
    if (!video.videoWidth || !video.videoHeight) return;
    const dpr = window.devicePixelRatio || 1;
    const bw = Math.max(1, Math.round(boxW * dpr));
    const bh = Math.max(1, Math.round(boxH * dpr));
    if (overlayPreviewCanvas.width !== bw || overlayPreviewCanvas.height !== bh) {
      overlayPreviewCanvas.width = bw;
      overlayPreviewCanvas.height = bh;
    }
    overlayPreviewCanvas.style.width = `${boxW}px`;
    overlayPreviewCanvas.style.height = `${boxH}px`;
    const ctx = overlayPreviewCanvas.getContext('2d', { alpha: true });
    if (!ctx) return;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    const rect = drawVideoLetterbox(ctx, video, boxW, boxH);
    if (
      rect &&
      liveOverlayImg &&
      liveOverlayImg.complete &&
      liveOverlayImg.naturalWidth > 0
    ) {
      ctx.drawImage(
        liveOverlayImg,
        0,
        0,
        liveOverlayImg.naturalWidth,
        liveOverlayImg.naturalHeight,
        rect.dx,
        rect.dy,
        rect.dw,
        rect.dh
      );
    }
  }

  function liveOverlayLoop() {
    liveOverlayRaf = 0;
    if (!liveCompositeShouldRun()) {
      hideLiveOverlayCanvas();
      return;
    }
    drawLiveCompositeFrame();
    liveOverlayRaf = requestAnimationFrame(liveOverlayLoop);
  }

  function showOverlayPreviewCanvas() {
    if (!overlayPreviewCanvas) return;
    overlayPreviewCanvas.hidden = false;
    overlayPreviewCanvas.classList.remove('hidden');
    overlayPreviewCanvas.setAttribute('aria-hidden', 'false');
  }

  function startLiveOverlayLoop() {
    if (!liveCompositeShouldRun()) return;
    if (webcamComposite) webcamComposite.classList.add('is-live-composite');
    showOverlayPreviewCanvas();
    if (!liveOverlayRaf) {
      liveOverlayRaf = requestAnimationFrame(liveOverlayLoop);
    }
  }

  function syncWebcamOverlayPreview() {
    if (!overlayPreviewCanvas || !video || !overlayInput || !panelWebcam) return;
    const camActive = !!mediaStream && !video.classList.contains('hidden');
    const webcamTab = !panelWebcam.hidden;
    const name = (overlayInput.value || '').trim();
    if (!camActive || !webcamTab || !name) {
      liveOverlayImg = null;
      liveOverlayUrl = '';
      hideLiveOverlayCanvas();
      return;
    }
    const url = overlayPreviewSrc(name);
    if (liveOverlayUrl === url && liveOverlayImg?.complete) {
      startLiveOverlayLoop();
      return;
    }
    liveOverlayUrl = url;
    if (liveCompositeShouldRun()) {
      if (webcamComposite) webcamComposite.classList.add('is-live-composite');
      showOverlayPreviewCanvas();
      if (!liveOverlayRaf) {
        liveOverlayRaf = requestAnimationFrame(liveOverlayLoop);
      }
    }
    const im = new Image();
    im.onload = () => {
      if (liveOverlayUrl !== url) return;
      liveOverlayImg = im;
      if (liveCompositeShouldRun()) {
        startLiveOverlayLoop();
      }
    };
    im.onerror = () => {
      if (liveOverlayUrl === url) {
        liveOverlayUrl = '';
        liveOverlayImg = null;
      }
      hideLiveOverlayCanvas();
    };
    im.src = url;
  }

  function stopWebcam() {
    if (mediaStream) {
      mediaStream.getTracks().forEach((t) => t.stop());
      mediaStream = null;
    }
    if (video) {
      video.srcObject = null;
      video.classList.add('hidden');
    }
    if (startBtn) startBtn.disabled = false;
    if (stopBtn) stopBtn.disabled = true;
    if (captureBtn) captureBtn.disabled = true;
    syncWebcamOverlayPreview();
  }

  function activateEditorTab(which) {
    const isWebcam = which === 'webcam';
    tabButtons.forEach((btn) => {
      const active = btn.dataset.tab === which;
      btn.classList.toggle('is-active', active);
      btn.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    if (panelWebcam) {
      panelWebcam.hidden = !isWebcam;
    }
    if (panelUpload) {
      panelUpload.hidden = isWebcam;
    }
    if (!isWebcam) {
      stopWebcam();
      updateCaptureButtonState();
    }
    syncWebcamOverlayPreview();
  }

  tabButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
      const tab = btn.dataset.tab;
      if (tab) {
        activateEditorTab(tab);
      }
    });
  });

  if (startBtn && video && canvas && captureBtn && stopBtn) {
    captureBtn.disabled = true;

    startBtn.addEventListener('click', async () => {
      try {
        const constraints = {
          audio: false,
          video: {
            facingMode: 'user',
            width: { ideal: 1280 },
            height: { ideal: 720 }
          }
        };

        try {
          mediaStream = await navigator.mediaDevices.getUserMedia(constraints);
        } catch {
          mediaStream = await navigator.mediaDevices.getUserMedia({ audio: false, video: true });
        }

        video.muted = true;
        video.defaultMuted = true;
        video.playsInline = true;
        video.setAttribute('playsinline', '');
        video.setAttribute('webkit-playsinline', '');
        video.srcObject = mediaStream;
        video.classList.remove('hidden');
        if (previewImage) previewImage.classList.add('hidden');
        capturedBlob = null;
        if (imageInput) imageInput.value = '';

        await video.play();

        startBtn.disabled = true;
        stopBtn.disabled = false;

        const onVideoReady = () => {
          syncWebcamOverlayPreview();
          updateCaptureButtonState();
        };
        if (video.videoWidth > 0) {
          onVideoReady();
        } else {
          video.addEventListener('loadedmetadata', onVideoReady, { once: true });
        }
        video.addEventListener('playing', onVideoReady, { once: true });
      } catch {
        alert('We could not use your camera. Try the upload tab instead.');
      }
    });

    stopBtn.addEventListener('click', () => {
      stopWebcam();
      updateCaptureButtonState();
    });

    captureBtn.addEventListener('click', async () => {
      if (!mediaStream) return;
      if (!video.videoWidth || !video.videoHeight) {
        alert('Give the preview a second to show up, then try again.');
        return;
      }
      if (!(overlayInput?.value || '').trim()) {
        alert('Pick a sticker first.');
        return;
      }
      captureBtn.disabled = true;
      try {
        const rawFrame = await captureWebcamFrameBlob();
        const base = await prepareBaseBlobForCommit(rawFrame);
        capturedBlob = base;
        if (imageInput) imageInput.value = '';
        hideLiveOverlayCanvas();
        stopWebcam();
        const ok = await commitEditorComposite(base);
        if (!ok) {
          capturedBlob = null;
        }
      } catch (err) {
        alert(err?.message || 'Something went wrong while capturing.');
      }
      updateCaptureButtonState();
    });
  }

  if (imageInput) {
    imageInput.addEventListener('change', async () => {
      capturedBlob = null;
      const file = imageInput.files?.[0];
      if (!file) {
        if (previewImage) previewImage.classList.add('hidden');
        return;
      }
      stopWebcam();
      if (!(overlayInput?.value || '').trim()) {
        alert('Pick a sticker first.');
        imageInput.value = '';
        return;
      }
      try {
        const base = await prepareBaseBlobForCommit(file);
        const ok = await commitEditorComposite(base);
        if (!ok) {
          imageInput.value = '';
        }
      } catch (err) {
        alert(err?.message || 'Could not process that file.');
        imageInput.value = '';
      }
    });
  }

  overlayButtons.forEach((button) => {
    button.addEventListener('click', async () => {
      overlayButtons.forEach((b) => b.classList.remove('active'));
      button.classList.add('active');

      const picked = button.dataset.overlay || '';
      overlayInput.value = picked;
      syncWebcamOverlayPreview();
      updateCaptureButtonState();

      if (createLocked && hasBaseImageSource()) {
        await recommitFromStoredBase();
      } else if (!createLocked && imageInput?.files?.[0] && (overlayInput.value || '').trim()) {
        const file = imageInput.files[0];
        try {
          const base = await prepareBaseBlobForCommit(file);
          await commitEditorComposite(base);
        } catch (err) {
          alert(err?.message || 'Could not update your photo.');
        }
      }
    });
  });

  if (editorForm && overlayInput && overlayButtons.length > 0 && !overlayInput.value.trim()) {
    overlayButtons.forEach((b) => b.classList.remove('active'));
    overlayInput.value = '';
    syncWebcamOverlayPreview();
    updateCaptureButtonState();
  }

  if (editorForm) {
    editorForm.addEventListener('submit', (e) => {
      e.preventDefault();
    });
  }

  async function postPendingImage() {
    if (!publishPendingBtn) return;
    publishPendingBtn.disabled = true;
    if (discardPendingBtn) discardPendingBtn.disabled = true;

    const pubResult = await fetchJson('api/publish_image.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: new URLSearchParams({ csrf_token: csrf })
    });
    if (!pubResult.ok) {
      alert(pubResult.reason === 'network' ? msgNetwork : msgBadJson);
      publishPendingBtn.disabled = false;
      if (discardPendingBtn) discardPendingBtn.disabled = false;
      return;
    }
    const data = pubResult.data;
    if (!data.success) {
      alert(data.error || 'Could not post to the gallery.');
      publishPendingBtn.disabled = false;
      if (discardPendingBtn) discardPendingBtn.disabled = false;
      return;
    }

    window.location.href = 'index.php?page=gallery';
  }

  async function discardPendingImage() {
    if (!discardPendingBtn) return;
    if (publishPendingBtn) publishPendingBtn.disabled = true;
    discardPendingBtn.disabled = true;

    const discResult = await fetchJson('api/discard_pending.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: new URLSearchParams({ csrf_token: csrf })
    });
    if (!discResult.ok) {
      alert(discResult.reason === 'network' ? msgNetwork : msgBadJson);
      if (publishPendingBtn) publishPendingBtn.disabled = false;
      discardPendingBtn.disabled = false;
      return;
    }
    const data = discResult.data;
    if (!data.success) {
      alert(data.error || 'Could not discard that draft.');
      if (publishPendingBtn) publishPendingBtn.disabled = false;
      discardPendingBtn.disabled = false;
      return;
    }

    if (resultImage) {
      resultImage.removeAttribute('src');
    }
    if (resultSection) {
      resultSection.classList.add('hidden');
    }
    if (resultActions) {
      resultActions.classList.add('hidden');
    }

    createLocked = false;
    capturedBlob = null;
    if (imageInput) imageInput.value = '';
    if (editorPreviewHost) editorPreviewHost.classList.remove('hidden');
    if (publishPendingBtn) publishPendingBtn.disabled = false;
    discardPendingBtn.disabled = false;
    updateCaptureButtonState();
  }

  if (publishPendingBtn) {
    publishPendingBtn.addEventListener('click', () => {
      postPendingImage();
    });
  }

  if (discardPendingBtn) {
    discardPendingBtn.addEventListener('click', () => {
      if (!confirm('Discard this image? It will not be added to the gallery.')) {
        return;
      }
      discardPendingImage();
    });
  }

  document.querySelectorAll('.like-btn').forEach((button) => {
    button.addEventListener('click', async () => {
      const imageId = button.dataset.imageId;

      const likeResult = await fetchJson('api/like.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
          csrf_token: csrf,
          image_id: imageId
        })
      });
      if (!likeResult.ok) {
        alert(likeResult.reason === 'network' ? msgNetwork : msgBadJson);
        return;
      }
      const data = likeResult.data;
      if (!data.success) {
        alert(data.error || 'Could not update the like. Try again.');
        return;
      }

      const countEl = document.getElementById(`like-count-${imageId}`);
      if (countEl) {
        countEl.textContent = String(data.count);
      }
    });
  });

  document.querySelectorAll('[data-comment-toggle]').forEach((btn) => {
    const panelId = btn.getAttribute('aria-controls');
    const panel = panelId ? document.getElementById(panelId) : null;
    if (!panel) return;
    btn.addEventListener('click', () => {
      const open = btn.classList.toggle('is-expanded');
      panel.hidden = !open;
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  });

  document.querySelectorAll('.comment-form').forEach((form) => {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      const formData = new FormData(form);
      formData.set('csrf_token', csrf);

      const commentResult = await fetchJson('api/comment.php', {
        method: 'POST',
        body: formData
      });
      if (!commentResult.ok) {
        alert(commentResult.reason === 'network' ? msgNetwork : msgBadJson);
        return;
      }
      const data = commentResult.data;
      if (!data.success) {
        alert(data.error || 'Could not post your comment.');
        return;
      }

      window.location.reload();
    });
  });

  document.querySelectorAll('.my-images-page__delete-form').forEach((form) => {
    form.addEventListener('submit', (e) => {
      if (!confirm('Delete this image from the gallery?')) {
        e.preventDefault();
      }
    });
  });
});
