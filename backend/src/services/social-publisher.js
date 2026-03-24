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

// ─── Instagram Publishing ──────────────────────────────────
async function publishToInstagram({ text, imagePath, videoPath }) {
  const config = getConfig();
  if (!config.instagramAccountId || !config.facebookPageToken) {
    console.warn('[Publisher] Instagram not configured — skipping');
    return null;
  }

  const token = config.facebookPageToken;
  const igId = config.instagramAccountId;

  // Instagram requires a public URL for the media
  // If running locally, the image needs to be served via the backend
  let mediaUrl = null;
  let isVideoPost = false;

  if (videoPath && isVideo(videoPath)) {
    isVideoPost = true;
    const filename = path.basename(videoPath);
    mediaUrl = `${config.backendUrl}/uploads/social/${filename}`;
  } else if (imagePath && fs.existsSync(imagePath)) {
    const filename = path.basename(imagePath);
    mediaUrl = `${config.backendUrl}/uploads/social/${filename}`;
  }

  if (!mediaUrl) {
    console.warn('[Publisher] Instagram requires an image or video — skipping');
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
