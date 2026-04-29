/* ============================================
   BAGEL BOYZ NJ - Main JavaScript
   ============================================ */

document.addEventListener('DOMContentLoaded', function () {

  // --- Navbar Scroll Effect ---
  const navbar = document.querySelector('.navbar');
  if (navbar) {
    window.addEventListener('scroll', function () {
      navbar.classList.toggle('scrolled', window.scrollY > 50);
    });
  }

  // --- Mobile Nav Toggle ---
  const navToggle = document.querySelector('.nav-toggle');
  const navLinks = document.querySelector('.nav-links');
  if (navToggle && navLinks) {
    navToggle.addEventListener('click', function () {
      navToggle.classList.toggle('active');
      navLinks.classList.toggle('active');
    });
    // Close nav on link click
    navLinks.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () {
        navToggle.classList.remove('active');
        navLinks.classList.remove('active');
      });
    });
    // Close nav on outside click
    document.addEventListener('click', function (e) {
      if (!navbar.contains(e.target)) {
        navToggle.classList.remove('active');
        navLinks.classList.remove('active');
      }
    });
  }

  // --- Active Nav Link ---
  const currentPage = window.location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('.nav-links a').forEach(function (link) {
    const href = link.getAttribute('href');
    if (href === currentPage || (currentPage === '' && href === 'index.html')) {
      link.classList.add('active');
    }
  });

  // --- Scroll Animations ---
  const animateElements = document.querySelectorAll('.animate-on-scroll');
  if (animateElements.length > 0) {
    const observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

    animateElements.forEach(function (el) {
      observer.observe(el);
    });
  }

  // --- FAQ Accordion ---
  document.querySelectorAll('.faq-question').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const item = this.closest('.faq-item');
      const isActive = item.classList.contains('active');
      // Close all
      document.querySelectorAll('.faq-item').forEach(function (i) {
        i.classList.remove('active');
      });
      // Toggle clicked
      if (!isActive) {
        item.classList.add('active');
      }
    });
  });

  // --- Menu Category Navigation ---
  document.querySelectorAll('.menu-nav-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const target = this.getAttribute('data-target');
      // Update active state
      document.querySelectorAll('.menu-nav-btn').forEach(function (b) {
        b.classList.remove('active');
      });
      this.classList.add('active');
      // Scroll to category
      const category = document.getElementById(target);
      if (category) {
        category.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });

  // --- reCAPTCHA Enterprise token helper ---
  function getRecaptchaToken(action) {
    var siteKey = window.BB_RECAPTCHA_SITE_KEY;
    if (!siteKey || typeof grecaptcha === 'undefined' || !grecaptcha.enterprise) {
      return Promise.resolve('');
    }
    return new Promise(function (resolve) {
      grecaptcha.enterprise.ready(function () {
        grecaptcha.enterprise.execute(siteKey, { action: action })
          .then(function (token) { resolve(token); })
          .catch(function () { resolve(''); });
      });
    });
  }

  // --- Form Handling ---
  document.querySelectorAll('form[data-ajax]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      const submitBtn = form.querySelector('button[type="submit"]');
      const originalText = submitBtn.textContent;
      submitBtn.textContent = 'Sending...';
      submitBtn.disabled = true;

      const action = form.getAttribute('data-recaptcha-action') || 'submit';

      getRecaptchaToken(action).then(function (token) {
        const formData = new FormData(form);
        if (token) formData.append('g-recaptcha-response', token);

        return fetch(form.action, {
          method: 'POST',
          body: formData
        });
      })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          const msgEl = form.querySelector('.form-message') || createFormMessage(form);
          if (data.success) {
            msgEl.className = 'form-message success';
            msgEl.textContent = data.message || 'Thank you! We\'ll be in touch soon.';
            form.reset();
          } else {
            msgEl.className = 'form-message error';
            msgEl.textContent = data.message || 'Something went wrong. Please try again.';
          }
          msgEl.style.display = 'block';
        })
        .catch(function () {
          const msgEl = form.querySelector('.form-message') || createFormMessage(form);
          msgEl.className = 'form-message error';
          msgEl.textContent = 'Network error. Please try again or call us directly.';
          msgEl.style.display = 'block';
        })
        .finally(function () {
          submitBtn.textContent = originalText;
          submitBtn.disabled = false;
        });
    });
  });

  function createFormMessage(form) {
    var msg = document.createElement('div');
    msg.className = 'form-message';
    msg.style.display = 'none';
    form.insertBefore(msg, form.firstChild);
    return msg;
  }

  // --- Smooth scroll for anchor links ---
  document.querySelectorAll('a[href^="#"]').forEach(function (link) {
    link.addEventListener('click', function (e) {
      var targetId = this.getAttribute('href');
      if (targetId === '#') return;
      var target = document.querySelector(targetId);
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });

  // --- Back to Top Button ---
  var backToTop = document.createElement('button');
  backToTop.className = 'back-to-top';
  backToTop.setAttribute('aria-label', 'Back to top');
  backToTop.innerHTML = '<i class="fas fa-chevron-up"></i>';
  document.body.appendChild(backToTop);

  window.addEventListener('scroll', function () {
    backToTop.classList.toggle('visible', window.scrollY > 400);
  });

  backToTop.addEventListener('click', function () {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });

  // --- Lazy loading for social feed images ---
  if ('IntersectionObserver' in window) {
    var lazyImages = document.querySelectorAll('img[data-src]');
    var imgObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          var img = entry.target;
          img.src = img.getAttribute('data-src');
          img.removeAttribute('data-src');
          imgObserver.unobserve(img);
        }
      });
    });
    lazyImages.forEach(function (img) {
      imgObserver.observe(img);
    });
  }

});
