/**
 * Bagel Boyz NJ - Social Publisher
 *
 * Publishes content to Facebook and Instagram via Meta Graph API.
 * Handles text-only, single image, and video posts.
 *
 * Adapted from BoatLife.ai social-publisher.js
 */

const axios = require('axios');
const FormData = require('form-data');
const fs = require('fs');
const path = require('path');

const GRAPH_API = 'https://graph.facebook.com/v21.0';

// ─── Config (loaded from .env or DB) ──────────────────────
// These should be set in .env or loaded from a database
function getConfig() {
  return {
    facebookPageId: process.env.FB_PAGE_ID || '',
    facebookPageToken: process.env.FB_PAGE_ACCESS_TOKEN || '',
    instagramAccountId: process.env.IG_ACCOUNT_ID || '',
    backendUrl: process.env.BACKEND_URL || 'http://localhost:4000'
  };
}

// ─── Helpers ───────────────────────────────────────────────
function isVideo(filePath) {
  if (!filePath) return false;
  const ext = path.extname(filePath).toLowerCase();
  return ['.mp4', '.mov', '.avi', '.webm'].includes(ext);
}

// ─── Facebook Publishing ───────────────────────────────────
async function publishToFacebook({ text, imagePath, videoPath }) {
  const config = getConfig();
  if (!config.facebookPageId || !config.facebookPageToken) {
    console.warn('[Publisher] Facebook not configured — skipping');
    return null;
  }

  const token = config.facebookPageToken;
  const pageId = config.facebookPageId;

  // Text-only post
  if (!imagePath && !videoPath) {
    const res = await axios.post(`${GRAPH_API}/${pageId}/feed`, {
      message: text,
      access_token: token
    });
    return { id: res.data.id, platform: 'facebook', type: 'text' };
  }

  // Video post
  if (videoPath && isVideo(videoPath)) {
    const form = new FormData();
    form.append('source', fs.createReadStream(videoPath));
    form.append('description', text);
    form.append('access_token', token);

    const res = await axios.post(`${GRAPH_API}/${pageId}/videos`, form, {
      headers: form.getHeaders(),
      maxContentLength: Infinity,
      maxBodyLength: Infinity,
      timeout: 120000
    });
    return { id: res.data.id, platform: 'facebook', type: 'video' };
  }

  // Single image post
  if (imagePath && fs.existsSync(imagePath)) {
    const form = new FormData();
    form.append('source', fs.createReadStream(imagePath));
    form.append('message', text);
    form.append('access_token', token);

    const res = await axios.post(`${GRAPH_API}/${pageId}/photos`, form, {
      headers: form.getHeaders(),
      timeout: 60000
    });
    return { id: res.data.id, platform: 'facebook', type: 'image' };
  }

  // Fallback: text-only
  const res = await axios.post(`${GRAPH_API}/${pageId}/feed`, {
    message: text,
    access_token: token
  });
  return { id: res.data.id, platform: 'facebook', type: 'text' };
}

// ─── Helper: Upload image to Facebook and get public URL ──
async function getPublicImageUrl(imagePath, token, pageId) {
  // Upload image to Facebook as unpublished, then retrieve its URL
  const form = new FormData();
  form.append('source', fs.createReadStream(imagePath));
  form.append('published', 'false');
  form.append('access_token', token);

  const uploadRes = await axios.post(`${GRAPH_API}/${pageId}/photos`, form, {
    headers: form.getHeaders(),
    timeout: 60000
  });

  const photoId = uploadRes.data.id;

  // Get the image URL from the uploaded photo
  const photoRes = await axios.get(`${GRAPH_API}/${photoId}`, {
    params: { fields: 'images', access_token: token }
  });

  // Return the largest image URL
  if (photoRes.data?.images?.length > 0) {
    return photoRes.data.images[0].source;
  }
  return null;
}

// ─── Instagram Publishing ──────────────────────────────────
async function publishToInstagram({ text, imagePath, videoPath }) {
  const config = getConfig();
  if (!config.instagramAccountId || !config.facebookPageToken) {
    console.warn('[Publisher] Instagram not configured — skipping');
    return null;
  }

  const token = config.facebookPageToken;
  const igId = config.instagramAccountId;
  const pageId = config.facebookPageId;

  let mediaUrl = null;
  let isVideoPost = false;

  if (videoPath && isVideo(videoPath)) {
    isVideoPost = true;
    // Videos need a publicly accessible URL — use backend URL if public, otherwise skip
    const filename = path.basename(videoPath);
    mediaUrl = `${config.backendUrl}/uploads/social/${filename}`;
  } else if (imagePath && fs.existsSync(imagePath)) {
    // Upload image to Facebook to get a public URL for Instagram
    console.log('[Publisher] Uploading image to Facebook for Instagram URL...');
    mediaUrl = await getPublicImageUrl(imagePath, token, pageId);
    if (mediaUrl) {
      console.log('[Publisher] Got public image URL for Instagram');
    }
  }

  if (!mediaUrl) {
    console.warn('[Publisher] Instagram requires a public media URL — skipping');
    return null;
  }

  // Step 1: Create media container
  const containerParams = {
    caption: text,
    access_token: token
  };

  if (isVideoPost) {
    containerParams.media_type = 'VIDEO';
    containerParams.video_url = mediaUrl;
  } else {
    containerParams.image_url = mediaUrl;
  }

  const containerRes = await axios.post(`${GRAPH_API}/${igId}/media`, containerParams);
  const containerId = containerRes.data.id;

  // Step 2: Poll for media to be ready
  let status = 'IN_PROGRESS';
  let attempts = 0;
  const maxAttempts = isVideoPost ? 30 : 10;
  const pollInterval = isVideoPost ? 5000 : 3000;

  while (status !== 'FINISHED' && attempts < maxAttempts) {
    await new Promise(resolve => setTimeout(resolve, pollInterval));
    attempts++;

    const statusRes = await axios.get(`${GRAPH_API}/${containerId}`, {
      params: { fields: 'status_code', access_token: token }
    });
    status = statusRes.data.status_code;

    if (status === 'ERROR') {
      throw new Error('Instagram media processing failed');
    }
  }

  if (status !== 'FINISHED') {
    throw new Error(`Instagram media not ready after ${maxAttempts} attempts`);
  }

  // Step 3: Publish
  const publishRes = await axios.post(`${GRAPH_API}/${igId}/media_publish`, {
    creation_id: containerId,
    access_token: token
  });

  return { id: publishRes.data.id, platform: 'instagram', type: isVideoPost ? 'video' : 'image' };
}

module.exports = { publishToFacebook, publishToInstagram };
