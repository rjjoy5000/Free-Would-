/* ============================================
   Free Would - Main JavaScript
   ============================================ */

// ── Constants ──────────────────────────────────
const API_BASE = 'backend';
const TOKEN_KEY = 'fw_token';
const USER_KEY = 'fw_user';

// ── Particle System ────────────────────────────
class ParticleSystem {
  constructor(canvasId) {
    this.canvas = document.getElementById(canvasId);
    if (!this.canvas) return;
    this.ctx = this.canvas.getContext('2d');
    this.particles = [];
    this.mouse = { x: null, y: null, radius: 150 };
    this.resize();
    this.init();
    this.animate();
    window.addEventListener('resize', () => this.resize());
    window.addEventListener('mousemove', (e) => {
      this.mouse.x = e.x;
      this.mouse.y = e.y;
    });
  }

  resize() {
    this.canvas.width = window.innerWidth;
    this.canvas.height = window.innerHeight;
  }

  init() {
    this.particles = [];
    const count = Math.min(80, Math.floor(window.innerWidth / 15));
    for (let i = 0; i < count; i++) {
      this.particles.push({
        x: Math.random() * this.canvas.width,
        y: Math.random() * this.canvas.height,
        size: Math.random() * 2 + 1,
        speedX: (Math.random() - 0.5) * 0.5,
        speedY: (Math.random() - 0.5) * 0.5,
        opacity: Math.random() * 0.5 + 0.2,
        color: Math.random() > 0.5 ? '#7C3AED' : '#06B6D4'
      });
    }
  }

  animate() {
    this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

    this.particles.forEach((p, i) => {
      // Move
      p.x += p.speedX;
      p.y += p.speedY;

      // Wrap around edges
      if (p.x < 0) p.x = this.canvas.width;
      if (p.x > this.canvas.width) p.x = 0;
      if (p.y < 0) p.y = this.canvas.height;
      if (p.y > this.canvas.height) p.y = 0;

      // Mouse repulsion
      if (this.mouse.x !== null) {
        const dx = p.x - this.mouse.x;
        const dy = p.y - this.mouse.y;
        const dist = Math.sqrt(dx * dx + dy * dy);
        if (dist < this.mouse.radius) {
          const force = (this.mouse.radius - dist) / this.mouse.radius;
          p.x += dx * force * 0.02;
          p.y += dy * force * 0.02;
        }
      }

      // Draw particle
      this.ctx.beginPath();
      this.ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
      this.ctx.fillStyle = p.color;
      this.ctx.globalAlpha = p.opacity;
      this.ctx.fill();
      this.ctx.globalAlpha = 1;

      // Connect nearby particles
      for (let j = i + 1; j < this.particles.length; j++) {
        const p2 = this.particles[j];
        const dx = p.x - p2.x;
        const dy = p.y - p2.y;
        const dist = Math.sqrt(dx * dx + dy * dy);
        if (dist < 150) {
          this.ctx.beginPath();
          this.ctx.moveTo(p.x, p.y);
          this.ctx.lineTo(p2.x, p2.y);
          this.ctx.strokeStyle = p.color;
          this.ctx.globalAlpha = (1 - dist / 150) * 0.15;
          this.ctx.lineWidth = 0.5;
          this.ctx.stroke();
          this.ctx.globalAlpha = 1;
        }
      }
    });

    requestAnimationFrame(() => this.animate());
  }
}

// ── Typing Effect ──────────────────────────────
class TypeWriter {
  constructor(elementId, texts, speed = 100) {
    this.element = document.getElementById(elementId);
    if (!this.element) return;
    this.texts = texts;
    this.speed = speed;
    this.textIndex = 0;
    this.charIndex = 0;
    this.isDeleting = false;
    this.type();
  }

  type() {
    const currentText = this.texts[this.textIndex];
    if (!currentText) return;

    if (this.isDeleting) {
      this.charIndex--;
      this.element.textContent = currentText.substring(0, this.charIndex);
    } else {
      this.charIndex++;
      this.element.textContent = currentText.substring(0, this.charIndex);
    }

    let delay = this.isDeleting ? this.speed / 2 : this.speed;

    if (!this.isDeleting && this.charIndex === currentText.length) {
      delay = 2000; // Pause at end
      this.isDeleting = true;
    } else if (this.isDeleting && this.charIndex === 0) {
      this.isDeleting = false;
      this.textIndex = (this.textIndex + 1) % this.texts.length;
      delay = 500;
    }

    setTimeout(() => this.type(), delay);
  }
}

// ── Counter Animation ──────────────────────────
function animateCounters() {
  const counters = document.querySelectorAll('[data-count]');
  if (!counters.length) return;

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const el = entry.target;
        const target = parseInt(el.getAttribute('data-count'));
        const suffix = el.getAttribute('data-suffix') || '';
        const duration = 2000;
        const start = 0;
        const startTime = performance.now();

        function update(currentTime) {
          const elapsed = currentTime - startTime;
          const progress = Math.min(elapsed / duration, 1);
          // Ease out cubic
          const eased = 1 - Math.pow(1 - progress, 3);
          const current = Math.floor(start + (target - start) * eased);
          el.textContent = formatNumber(current) + suffix;

          if (progress < 1) {
            requestAnimationFrame(update);
          } else {
            el.textContent = formatNumber(target) + suffix;
          }
        }

        requestAnimationFrame(update);
        observer.unobserve(el);
      }
    });
  }, { threshold: 0.5 });

  counters.forEach(counter => observer.observe(counter));
}

// ── Navbar ─────────────────────────────────────
function initNavbar() {
  const navbar = document.querySelector('.navbar');
  if (!navbar) return;

  // Sticky on scroll
  window.addEventListener('scroll', () => {
    if (window.scrollY > 50) {
      navbar.classList.add('scrolled');
    } else {
      navbar.classList.remove('scrolled');
    }
  });

  // Active link based on scroll position
  const sections = document.querySelectorAll('section[id]');
  const navLinks = document.querySelectorAll('.nav-link[href^="#"]');

  if (sections.length && navLinks.length) {
    window.addEventListener('scroll', () => {
      let current = '';
      sections.forEach(section => {
        const sectionTop = section.offsetTop - 100;
        if (window.scrollY >= sectionTop) {
          current = section.getAttribute('id');
        }
      });

      navLinks.forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('href') === '#' + current) {
          link.classList.add('active');
        }
      });
    });
  }
}

// ── Scroll Animations ──────────────────────────
function initScrollAnimations() {
  const elements = document.querySelectorAll('[data-aos]');
  if (!elements.length) return;

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

  elements.forEach(el => {
    el.classList.add('aos-init');
    observer.observe(el);
  });
}

// ── Testimonials Slider ────────────────────────
class TestimonialSlider {
  constructor(containerId) {
    this.container = document.getElementById(containerId);
    if (!this.container) return;

    this.track = this.container.querySelector('.testimonial-track');
    this.cards = this.container.querySelectorAll('.testimonial-card');
    this.dotsContainer = this.container.querySelector('.testimonial-dots');
    this.prevBtn = this.container.querySelector('.testimonial-prev');
    this.nextBtn = this.container.querySelector('.testimonial-next');

    if (!this.cards.length) return;

    this.currentIndex = 0;
    this.autoPlayInterval = null;
    this.touchStartX = 0;
    this.touchEndX = 0;

    this.init();
  }

  init() {
    // Create dots
    if (this.dotsContainer) {
      this.dotsContainer.innerHTML = '';
      this.cards.forEach((_, i) => {
        const dot = document.createElement('span');
        dot.classList.add('dot');
        if (i === 0) dot.classList.add('active');
        dot.addEventListener('click', () => this.goTo(i));
        this.dotsContainer.appendChild(dot);
      });
      this.dots = this.dotsContainer.querySelectorAll('.dot');
    }

    // Button events
    if (this.prevBtn) this.prevBtn.addEventListener('click', () => this.prev());
    if (this.nextBtn) this.nextBtn.addEventListener('click', () => this.next());

    // Touch swipe
    this.container.addEventListener('touchstart', (e) => {
      this.touchStartX = e.changedTouches[0].screenX;
    }, { passive: true });

    this.container.addEventListener('touchend', (e) => {
      this.touchEndX = e.changedTouches[0].screenX;
      this.handleSwipe();
    }, { passive: true });

    // Auto play
    this.startAutoPlay();
    this.container.addEventListener('mouseenter', () => this.stopAutoPlay());
    this.container.addEventListener('mouseleave', () => this.startAutoPlay());

    this.update();
  }

  goTo(index) {
    this.currentIndex = index;
    if (this.currentIndex < 0) this.currentIndex = this.cards.length - 1;
    if (this.currentIndex >= this.cards.length) this.currentIndex = 0;
    this.update();
  }

  next() {
    this.goTo(this.currentIndex + 1);
  }

  prev() {
    this.goTo(this.currentIndex - 1);
  }

  handleSwipe() {
    const diff = this.touchStartX - this.touchEndX;
    if (Math.abs(diff) > 50) {
      if (diff > 0) this.next();
      else this.prev();
    }
  }

  update() {
    if (this.track) {
      this.track.style.transform = `translateX(-${this.currentIndex * 100}%)`;
    }

    if (this.dots) {
      this.dots.forEach((dot, i) => {
        dot.classList.toggle('active', i === this.currentIndex);
      });
    }
  }

  startAutoPlay() {
    this.stopAutoPlay();
    this.autoPlayInterval = setInterval(() => this.next(), 5000);
  }

  stopAutoPlay() {
    if (this.autoPlayInterval) {
      clearInterval(this.autoPlayInterval);
      this.autoPlayInterval = null;
    }
  }
}

// ── FAQ Accordion ──────────────────────────────
function initFAQ() {
  const items = document.querySelectorAll('.faq-item');
  if (!items.length) return;

  items.forEach(item => {
    const question = item.querySelector('.faq-question');
    if (!question) return;

    question.addEventListener('click', () => {
      const isActive = item.classList.contains('active');

      // Close all others
      items.forEach(other => {
        if (other !== item) {
          other.classList.remove('active');
          const otherAnswer = other.querySelector('.faq-answer');
          if (otherAnswer) otherAnswer.style.maxHeight = null;
        }
      });

      // Toggle current
      item.classList.toggle('active', !isActive);
      const answer = item.querySelector('.faq-answer');
      if (answer) {
        if (!isActive) {
          answer.style.maxHeight = answer.scrollHeight + 'px';
        } else {
          answer.style.maxHeight = null;
        }
      }
    });
  });
}

// ── Pricing Toggle ─────────────────────────────
function initPricingToggle() {
  const toggle = document.getElementById('pricing-toggle');
  if (!toggle) return;

  const monthlyPrices = document.querySelectorAll('[data-monthly]');
  const yearlyPrices = document.querySelectorAll('[data-yearly]');

  toggle.addEventListener('change', () => {
    const isYearly = toggle.checked;
    monthlyPrices.forEach(el => {
      el.textContent = isYearly ? el.getAttribute('data-yearly') : el.getAttribute('data-monthly');
    });

    // Toggle badges
    document.querySelectorAll('.yearly-badge').forEach(badge => {
      badge.style.display = isYearly ? 'inline-block' : 'none';
    });
  });
}

// ── Mobile Menu ────────────────────────────────
function initMobileMenu() {
  const hamburger = document.querySelector('.hamburger');
  const navMenu = document.querySelector('.nav-menu');
  if (!hamburger || !navMenu) return;

  hamburger.addEventListener('click', () => {
    hamburger.classList.toggle('active');
    navMenu.classList.toggle('active');
    document.body.classList.toggle('menu-open');
  });

  // Close on link click
  navMenu.querySelectorAll('.nav-link').forEach(link => {
    link.addEventListener('click', () => {
      hamburger.classList.remove('active');
      navMenu.classList.remove('active');
      document.body.classList.remove('menu-open');
    });
  });

  // Close on outside click
  document.addEventListener('click', (e) => {
    if (!hamburger.contains(e.target) && !navMenu.contains(e.target)) {
      hamburger.classList.remove('active');
      navMenu.classList.remove('active');
      document.body.classList.remove('menu-open');
    }
  });
}

// ── Smooth Scroll ──────────────────────────────
function initSmoothScroll() {
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', (e) => {
      const href = anchor.getAttribute('href');
      if (href === '#') return;
      e.preventDefault();
      const target = document.querySelector(href);
      if (target) {
        const navHeight = document.querySelector('.navbar')?.offsetHeight || 80;
        const targetPos = target.getBoundingClientRect().top + window.scrollY - navHeight;
        window.scrollTo({ top: targetPos, behavior: 'smooth' });
      }
    });
  });
}

// ── Loading States ─────────────────────────────
function showLoader(elementId) {
  const el = document.getElementById(elementId);
  if (!el) return;
  el.classList.add('loading');
  el.disabled = true;
  const spinner = document.createElement('span');
  spinner.classList.add('spinner');
  el.prepend(spinner);
}

function hideLoader(elementId) {
  const el = document.getElementById(elementId);
  if (!el) return;
  el.classList.remove('loading');
  el.disabled = false;
  const spinner = el.querySelector('.spinner');
  if (spinner) spinner.remove();
}

function showToast(message, type = 'success') {
  const existing = document.querySelector('.toast');
  if (existing) existing.remove();

  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.innerHTML = `
    <div class="toast-content">
      <span class="toast-icon">${type === 'success' ? '&#10004;' : type === 'error' ? '&#10006;' : '&#9432;'}</span>
      <span class="toast-message">${message}</span>
    </div>
    <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
  `;

  document.body.appendChild(toast);

  // Trigger animation
  requestAnimationFrame(() => toast.classList.add('show'));

  // Auto remove
  setTimeout(() => {
    toast.classList.remove('show');
    setTimeout(() => toast.remove(), 300);
  }, 4000);
}

// ── Ripple Effect ──────────────────────────────
function initRippleButtons() {
  document.querySelectorAll('.btn, .ripple').forEach(btn => {
    btn.addEventListener('click', function(e) {
      const rect = this.getBoundingClientRect();
      const ripple = document.createElement('span');
      ripple.classList.add('ripple-effect');
      ripple.style.left = (e.clientX - rect.left) + 'px';
      ripple.style.top = (e.clientY - rect.top) + 'px';
      this.appendChild(ripple);
      setTimeout(() => ripple.remove(), 600);
    });
  });
}

// ── Utility Functions ──────────────────────────
function formatDate(dateString) {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    year: 'numeric', month: 'short', day: 'numeric',
    hour: '2-digit', minute: '2-digit'
  });
}

function formatNumber(num) {
  if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
  if (num >= 1000) return num.toLocaleString();
  return num.toString();
}

function debounce(func, wait = 300) {
  let timeout;
  return function executedFunction(...args) {
    clearTimeout(timeout);
    timeout = setTimeout(() => func.apply(this, args), wait);
  };
}

// ── Auth Utilities ─────────────────────────────
function getAuthToken() {
  return localStorage.getItem(TOKEN_KEY);
}

function setAuthToken(token) {
  localStorage.setItem(TOKEN_KEY, token);
}

function setUser(user) {
  localStorage.setItem(USER_KEY, JSON.stringify(user));
}

function getUser() {
  try {
    return JSON.parse(localStorage.getItem(USER_KEY));
  } catch {
    return null;
  }
}

function clearAuth() {
  localStorage.removeItem(TOKEN_KEY);
  localStorage.removeItem(USER_KEY);
}

function isLoggedIn() {
  return !!getAuthToken();
}

function redirectIfAuth() {
  if (isLoggedIn()) {
    window.location.href = 'dashboard.html';
  }
}

function requireAuth() {
  if (!isLoggedIn()) {
    window.location.href = 'login.html';
    return false;
  }
  return true;
}

function updateNavAuth() {
  const authBtns = document.querySelector('.nav-auth');
  if (!authBtns) return;

  if (isLoggedIn()) {
    const user = getUser();
    authBtns.innerHTML = `
      <a href="dashboard.html" class="btn btn-outline">Dashboard</a>
      <div class="user-avatar-nav" onclick="window.location.href='profile.html'">
        <span>${(user?.name || 'U').charAt(0).toUpperCase()}</span>
      </div>
    `;
  }
}

// ── API Helper ─────────────────────────────────
async function apiCall(endpoint, method = 'GET', data = null) {
  const token = getAuthToken();
  const options = {
    method,
    headers: {
      'Content-Type': 'application/json',
      ...(token ? { 'Authorization': `Bearer ${token}` } : {})
    }
  };

  if (data && method !== 'GET') {
    options.body = JSON.stringify(data);
  }

  try {
    const response = await fetch(`${API_BASE}/${endpoint}`, options);
    const result = await response.json();

    if (!response.ok) {
      if (response.status === 401) {
        clearAuth();
        if (!window.location.pathname.includes('login')) {
          window.location.href = 'login.html';
        }
      }
      throw new Error(result.message || 'Request failed');
    }

    return result;
  } catch (error) {
    if (error.message === 'Failed to fetch') {
      throw new Error('Network error. Please check your connection.');
    }
    throw error;
  }
}

// ── Copy to Clipboard ──────────────────────────
function copyToClipboard(text) {
  navigator.clipboard.writeText(text).then(() => {
    showToast('Copied to clipboard!', 'success');
  }).catch(() => {
    // Fallback
    const textarea = document.createElement('textarea');
    textarea.value = text;
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    textarea.remove();
    showToast('Copied to clipboard!', 'success');
  });
}

// ── Dropdown Toggle ────────────────────────────
function initDropdowns() {
  document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
    toggle.addEventListener('click', (e) => {
      e.stopPropagation();
      const menu = toggle.nextElementSibling;
      menu.classList.toggle('show');
    });
  });

  document.addEventListener('click', () => {
    document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
      menu.classList.remove('show');
    });
  });
}

// ── Tabs ───────────────────────────────────────
function initTabs() {
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const target = btn.getAttribute('data-tab');
      const container = btn.closest('.tabs-container');
      if (!container) return;

      // Update buttons
      container.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      // Update panels
      container.querySelectorAll('.tab-panel').forEach(panel => {
        panel.classList.toggle('active', panel.id === target);
      });
    });
  });
}

// ── Modal ──────────────────────────────────────
function openModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
  }
}

function closeModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.remove('show');
    document.body.style.overflow = '';
  }
}

// Close modal on outside click
document.addEventListener('click', (e) => {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('show');
    document.body.style.overflow = '';
  }
});

// ── Confirm Dialog ─────────────────────────────
function confirmAction(message) {
  return new Promise(resolve => {
    const modal = document.createElement('div');
    modal.className = 'modal-overlay show';
    modal.innerHTML = `
      <div class="modal confirm-modal">
        <div class="modal-body">
          <p>${message}</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline cancel-btn">Cancel</button>
          <button class="btn btn-danger confirm-btn">Confirm</button>
        </div>
      </div>
    `;
    document.body.appendChild(modal);

    modal.querySelector('.cancel-btn').addEventListener('click', () => {
      modal.remove();
      resolve(false);
    });

    modal.querySelector('.confirm-btn').addEventListener('click', () => {
      modal.remove();
      resolve(true);
    });
  });
}

// ── Initialize Everything ──────────────────────
document.addEventListener('DOMContentLoaded', () => {
  // Particle background
  new ParticleSystem('particle-canvas');

  // Typing effect
  new TypeWriter('typing-text', [
    'Generate stunning images with AI...',
    'Create amazing videos from text...',
    'Chat with advanced AI models...',
    'Unleash your creativity with AI...'
  ], 80);

  // Counters
  animateCounters();

  // Navbar
  initNavbar();

  // Testimonials
  new TestimonialSlider('testimonial-slider');

  // FAQ
  initFAQ();

  // Pricing
  initPricingToggle();

  // Scroll animations
  initScrollAnimations();

  // Mobile menu
  initMobileMenu();

  // Smooth scroll
  initSmoothScroll();

  // Ripple buttons
  initRippleButtons();

  // Dropdowns
  initDropdowns();

  // Tabs
  initTabs();

  // Auth state
  updateNavAuth();
});
