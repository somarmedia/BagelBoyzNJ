/**
 * Bagel Boyz NJ - PM2 Ecosystem Configuration
 * Social media automation scheduling
 */

module.exports = {
  apps: [
    // ─── Social Autopilot ────────────────────────────────
    // Posts 3x daily: 7 AM (breakfast rush), 11:30 AM (lunch), 4 PM (afternoon)
    {
      name: 'bb-social-autopilot',
      script: 'src/workers/social-autopilot.js',
      cwd: __dirname,
      cron_restart: '0 7 * * *,30 11 * * *,0 16 * * *',
      autorestart: false,
      watch: false,
      max_memory_restart: '500M',
      env: {
        NODE_ENV: 'production',
        TZ: 'America/New_York'
      },
      log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
      error_file: 'logs/social-autopilot-error.log',
      out_file: 'logs/social-autopilot-out.log',
      merge_logs: true
    },

    // ─── Social Feed Generator ───────────────────────────
    // Generates static JSON for the website social feed page
    // Runs every 2 hours to keep the social.html page updated
    {
      name: 'bb-feed-generator',
      script: 'src/workers/feed-generator.js',
      cwd: __dirname,
      cron_restart: '0 */2 * * *',
      autorestart: false,
      watch: false,
      max_memory_restart: '200M',
      env: {
        NODE_ENV: 'production',
        TZ: 'America/New_York'
      },
      log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
      error_file: 'logs/feed-generator-error.log',
      out_file: 'logs/feed-generator-out.log',
      merge_logs: true
    }
  ]
};
