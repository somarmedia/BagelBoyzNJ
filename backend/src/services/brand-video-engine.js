/**
 * Bagel Boyz NJ - Brand Video Engine
 *
 * Generates short-form video content for Instagram Reels, Facebook Reels, and TikTok.
 * Pipeline: 3 Kling clips -> VO script -> TTS -> FFmpeg concat + overlay
 *
 * Adapted from BoatLife.ai brand-video-engine.js
 */

const axios = require('axios');
const fs = require('fs');
const path = require('path');
const { v4: uuidv4 } = require('uuid');

// ─── Video Scene Libraries ─────────────────────────────────

const ORGANIC_VIDEO_SCENES = [
  {
    type: 'morning_ritual',
    prompt: 'A warm, cinematic shot of a bagel shop opening at dawn. Golden morning light streaming through windows. Fresh bagels being arranged on display. Steam rising from coffee. Cozy New Jersey breakfast atmosphere.',
    mood: 'warm, inviting, peaceful'
  },
  {
    type: 'the_craft',
    prompt: 'Close-up of bagel dough being hand-shaped into rings. Bagels dropping into boiling water, steam billowing. Golden bagels emerging from the oven on wooden paddles. Artisan food craftsmanship.',
    mood: 'craftsmanship, pride, artisan'
  },
  {
    type: 'sandwich_build',
    prompt: 'Satisfying close-up of a breakfast sandwich being assembled: Taylor ham sizzling on the grill, egg cracking, cheese melting, sandwich being wrapped. Fast-paced, appetizing food content.',
    mood: 'appetizing, satisfying, fast-paced'
  },
  {
    type: 'community',
    prompt: 'A busy New Jersey bagel shop during the morning rush. Diverse customers ordering, chatting, enjoying breakfast. Friendly staff behind the counter. Neighborhood energy.',
    mood: 'community, energy, belonging'
  },
  {
    type: 'cream_cheese',
    prompt: 'Close-up of cream cheese being generously spread on a perfectly toasted everything bagel. Multiple cream cheese varieties displayed. Colorful, appetizing spread of toppings and flavors.',
    mood: 'indulgent, variety, satisfaction'
  },
  {
    type: 'jersey_pride',
    prompt: 'Aerial shot of New Jersey suburban streets at sunrise. Cut to a bagel shop facade. Cut to a Taylor ham egg and cheese being held up with pride. New Jersey morning culture.',
    mood: 'pride, local, authentic'
  }
];

const AD_VIDEO_SCENES = [
  {
    type: 'fomo',
    prompt: 'Fresh bagels flying off the rack. Customers smiling as they grab bags of bagels. "Going fast" energy — the morning rush at a popular NJ bagel shop.',
    mood: 'urgency, popularity, FOMO'
  },
  {
    type: 'catering',
    prompt: 'A beautifully arranged catering spread: tiered bagel platters, sandwich trays, cream cheese displays, and coffee service. Corporate meeting or party setting.',
    mood: 'professional, impressive, abundant'
  },
  {
    type: 'comparison',
    prompt: 'Split screen: a sad chain-store bagel (pale, flat) vs a glorious NJ bagel (golden, perfectly risen, seeds glistening). Night and day difference. Real vs fake.',
    mood: 'contrast, superiority, authenticity'
  }
];

// ─── Kling Video Generation ────────────────────────────────
async function generateKlingClip(scenePrompt, aspectRatio = '9:16') {
  if (!process.env.KIE_API_KEY) {
    console.warn('[VideoEngine] KIE_API_KEY not set — skipping video generation');
    return null;
  }

  try {
    // Submit generation request
    const submitRes = await axios.post('https://api.kie.ai/v1/video/generate', {
      prompt: scenePrompt,
      model: 'kling-3.0',
      duration: 5,
      aspect_ratio: aspectRatio,
      mode: 'standard'
    }, {
      headers: {
        'Authorization': `Bearer ${process.env.KIE_API_KEY}`,
        'Content-Type': 'application/json'
      },
      timeout: 30000
    });

    const taskId = submitRes.data?.data?.task_id;
    if (!taskId) throw new Error('No task_id returned from Kling');

    // Poll for completion (up to 5 minutes)
    let videoUrl = null;
    for (let i = 0; i < 60; i++) {
      await new Promise(r => setTimeout(r, 5000));

      const statusRes = await axios.get(`https://api.kie.ai/v1/video/status/${taskId}`, {
        headers: { 'Authorization': `Bearer ${process.env.KIE_API_KEY}` }
      });

      const status = statusRes.data?.data?.status;
      if (status === 'completed') {
        videoUrl = statusRes.data?.data?.video_url;
        break;
      }
      if (status === 'failed') throw new Error('Kling generation failed');
    }

    if (!videoUrl) throw new Error('Kling generation timed out');

    // Download video
    const videoRes = await axios.get(videoUrl, { responseType: 'arraybuffer', timeout: 60000 });
    return Buffer.from(videoRes.data);
  } catch (err) {
    console.error('[VideoEngine] Kling clip failed:', err.message);
    return null;
  }
}

// ─── Voiceover Script Generation ───────────────────────────
async function generateVOScript(sceneType, category) {
  const callAI = require('./social-content-generator').callAI;

  const systemPrompt = `You are a voiceover scriptwriter for Bagel Boyz NJ, a family-owned bagel shop in Hazlet, New Jersey.

Write a 15-second voiceover script (about 35-40 words) that is:
- Warm, casual, authentic NJ voice
- Makes the viewer hungry
- Mentions Bagel Boyz naturally
- Ends with a soft CTA

DO NOT use corporate language. Sound like a real person talking about their favorite bagel spot.`;

  const prompt = `Write a 15-second voiceover for a ${sceneType} video about ${category}.
Return ONLY the voiceover text — no labels, no stage directions.`;

  return callAI(systemPrompt, prompt, { maxTokens: 100 });
}

// ─── TTS (Text-to-Speech) ──────────────────────────────────
async function generateTTS(script, outputPath) {
  // Try HeyGen first
  if (process.env.HEYGEN_API_KEY) {
    try {
      const res = await axios.post('https://api.heygen.com/v2/voice/generate', {
        text: script,
        voice_id: 'Sammy', // Warm, friendly male voice
        speed: 1.0
      }, {
        headers: {
          'Authorization': `Bearer ${process.env.HEYGEN_API_KEY}`,
          'Content-Type': 'application/json'
        },
        responseType: 'arraybuffer',
        timeout: 30000
      });

      fs.writeFileSync(outputPath, Buffer.from(res.data));
      return outputPath;
    } catch (err) {
      console.warn('[VideoEngine] HeyGen TTS failed:', err.message);
    }
  }

  // Fallback: OpenAI TTS
  if (process.env.OPENAI_API_KEY) {
    try {
      const res = await axios.post('https://api.openai.com/v1/audio/speech', {
        model: 'tts-1',
        input: script,
        voice: 'onyx', // Warm male voice
        speed: 1.0
      }, {
        headers: {
          'Authorization': `Bearer ${process.env.OPENAI_API_KEY}`,
          'Content-Type': 'application/json'
        },
        responseType: 'arraybuffer',
        timeout: 30000
      });

      fs.writeFileSync(outputPath, Buffer.from(res.data));
      return outputPath;
    } catch (err) {
      console.warn('[VideoEngine] OpenAI TTS failed:', err.message);
    }
  }

  console.warn('[VideoEngine] No TTS service available');
  return null;
}

// ─── FFmpeg Video Assembly ─────────────────────────────────
async function assembleVideo({ clips, audioPath, headline, outputPath, aspectRatio = '9:16' }) {
  const ffmpeg = require('fluent-ffmpeg');

  return new Promise((resolve, reject) => {
    // Create concat file list
    const concatDir = path.dirname(outputPath);
    const concatFile = path.join(concatDir, `concat-${uuidv4()}.txt`);
    const concatContent = clips.map(c => `file '${c}'`).join('\n');
    fs.writeFileSync(concatFile, concatContent);

    let cmd = ffmpeg()
      .input(concatFile)
      .inputOptions(['-f', 'concat', '-safe', '0']);

    if (audioPath && fs.existsSync(audioPath)) {
      cmd = cmd.input(audioPath);
    }

    // Determine resolution
    const [aw, ah] = aspectRatio === '9:16' ? [1080, 1920] : aspectRatio === '4:5' ? [1080, 1350] : [1920, 1080];

    cmd
      .outputOptions([
        '-c:v', 'libx264',
        '-pix_fmt', 'yuv420p',
        '-crf', '23',
        '-preset', 'fast',
        `-vf`, `scale=${aw}:${ah}:force_original_aspect_ratio=decrease,pad=${aw}:${ah}:(ow-iw)/2:(oh-ih)/2`,
        '-shortest',
        '-movflags', '+faststart'
      ])
      .output(outputPath)
      .on('end', () => {
        // Cleanup concat file
        try { fs.unlinkSync(concatFile); } catch (e) { /* ignore */ }
        resolve(outputPath);
      })
      .on('error', (err) => {
        try { fs.unlinkSync(concatFile); } catch (e) { /* ignore */ }
        reject(err);
      })
      .run();
  });
}

// ─── Main: Generate Video ──────────────────────────────────
async function generateBrandVideo({ category, headline, outputDir, postId, type = 'organic' }) {
  const scenePool = type === 'ad' ? AD_VIDEO_SCENES : ORGANIC_VIDEO_SCENES;

  // Pick 3 diverse scenes
  const shuffled = [...scenePool].sort(() => Math.random() - 0.5);
  const scenes = shuffled.slice(0, 3);

  console.log(`[VideoEngine] Generating ${type} video with scenes: ${scenes.map(s => s.type).join(', ')}`);

  // Generate 3 clips in parallel
  const clipPromises = scenes.map(async (scene, i) => {
    const clip = await generateKlingClip(scene.prompt);
    if (clip) {
      const clipPath = path.join(outputDir, `${postId}-clip-${i}.mp4`);
      fs.writeFileSync(clipPath, clip);
      return clipPath;
    }
    return null;
  });

  const clips = (await Promise.all(clipPromises)).filter(Boolean);

  if (clips.length === 0) {
    console.warn('[VideoEngine] No clips generated — skipping video');
    return null;
  }

  // Generate voiceover
  const voScript = await generateVOScript(scenes[0].type, category);
  let audioPath = null;

  if (voScript) {
    audioPath = path.join(outputDir, `${postId}-vo.mp3`);
    audioPath = await generateTTS(voScript, audioPath);
  }

  // Assemble final video
  const outputPath = path.join(outputDir, `${postId}-video.mp4`);

  try {
    await assembleVideo({
      clips,
      audioPath,
      headline,
      outputPath,
      aspectRatio: '9:16'
    });
    console.log(`[VideoEngine] Video assembled: ${outputPath}`);

    // Cleanup clip files
    clips.forEach(c => { try { fs.unlinkSync(c); } catch (e) { /* ignore */ } });
    if (audioPath) try { fs.unlinkSync(audioPath); } catch (e) { /* ignore */ }

    return outputPath;
  } catch (err) {
    console.error('[VideoEngine] Assembly failed:', err.message);
    return null;
  }
}

module.exports = {
  generateBrandVideo,
  generateKlingClip,
  generateVOScript,
  ORGANIC_VIDEO_SCENES,
  AD_VIDEO_SCENES
};
