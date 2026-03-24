#!/usr/bin/env node
/**
 * Bagel Boyz NJ - Social Autopilot Worker
 *
 * Runs 3x daily via PM2 cron: 7 AM, 11:30 AM, 4 PM EST
 * Each run generates content for a different time-of-day context,
 * creates AI-generated food photography, and publishes to Facebook + Instagram.
 *
 * Adapted from BoatLife.ai social autopilot architecture.
 */

require('dotenv').config({ path: require('path').join(__dirname, '../../.env') });

const { runSocialAutopilot } = require('../services/social-content-generator');

(async () => {
  const hour = new Date().getHours();
  let runIndex;

  if (hour < 10) {
    runIndex = 0; // Morning post (7 AM) — breakfast focus
  } else if (hour < 14) {
    runIndex = 1; // Midday post (11:30 AM) — lunch / catering focus
  } else {
    runIndex = 2; // Afternoon post (4 PM) — next-day hype / culture
  }

  console.log(`[BagelBoyz Autopilot] Starting run #${runIndex} at ${new Date().toLocaleString('en-US', { timeZone: 'America/New_York' })}`);

  try {
    await runSocialAutopilot(runIndex);
    console.log('[BagelBoyz Autopilot] Run completed successfully.');
  } catch (err) {
    console.error('[BagelBoyz Autopilot] Fatal error:', err);
    process.exitCode = 1;
  }

  // PM2 cron mode: exit so PM2 can restart at next scheduled time
  process.exit(process.exitCode || 0);
})();
