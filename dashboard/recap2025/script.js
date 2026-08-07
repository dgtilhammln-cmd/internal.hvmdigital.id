const audio = document.getElementById('bgMusic');
const volBtn = document.getElementById('volIcon');
let currentSlide = 1;
const totalSlides = 6; // Updated total slides

// PARTICLE SYSTEM
const canvas = document.getElementById('cosmos');
const ctx = canvas.getContext('2d');
canvas.width = window.innerWidth; canvas.height = window.innerHeight;
let particles = [];

class Particle {
    constructor() {
        this.x = Math.random() * canvas.width;
        this.y = Math.random() * canvas.height;
        this.size = Math.random() * 2;
        this.speedX = (Math.random() - 0.5) * 0.5;
        this.speedY = (Math.random() - 0.5) * 0.5;
        this.opacity = Math.random();
    }
    update() {
        this.x += this.speedX; this.y += this.speedY;
        if (this.x > canvas.width) this.x = 0; if (this.x < 0) this.x = canvas.width;
        if (this.y > canvas.height) this.y = 0; if (this.y < 0) this.y = canvas.height;
    }
    draw() {
        ctx.fillStyle = `rgba(161, 255, 90, ${this.opacity})`;
        ctx.beginPath(); ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2); ctx.fill();
    }
}
for (let i=0; i<120; i++) particles.push(new Particle());
function animate() { ctx.clearRect(0,0,canvas.width,canvas.height); particles.forEach(p=>{p.update();p.draw();}); requestAnimationFrame(animate); }
animate();

// NAVIGATION
function startExperience() {
    audio.volume = 0.6;
    audio.play().catch(e => console.log("Audio need interaction"));
    goToSlide(2);
}

function nextSlide() { if(currentSlide < totalSlides) goToSlide(currentSlide + 1); }
function prevSlide() { if(currentSlide > 1) goToSlide(currentSlide - 1); }

function goToSlide(n) {
    document.getElementById(`slide${currentSlide}`).classList.remove('active');
    document.getElementById(`slide${currentSlide}`).classList.add('passed');
    
    setTimeout(() => {
        currentSlide = n;
        const nextEl = document.getElementById(`slide${currentSlide}`);
        nextEl.classList.remove('passed');
        nextEl.classList.add('active');
        triggerCounters(nextEl);
    }, 400);
}

function triggerCounters(slide) {
    slide.querySelectorAll('.counter-num, .counter-simple').forEach(c => {
        animateValue(c, 0, parseInt(c.getAttribute('data-val')), 2000);
    });
}

function animateValue(obj, start, end, duration) {
    let startTimestamp = null;
    const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        const ease = 1 - Math.pow(1 - progress, 3);
        obj.innerHTML = new Intl.NumberFormat('id-ID').format(Math.floor(ease * (end - start) + start));
        if (progress < 1) window.requestAnimationFrame(step);
    };
    window.requestAnimationFrame(step);
}

function toggleMute() {
    if(audio.paused) { audio.play(); volBtn.className = 'fas fa-volume-up'; }
    else { audio.pause(); volBtn.className = 'fas fa-volume-mute'; }
}

// SHARE FUNCTION
function downloadStory() {
    const element = document.querySelector("#storyCapture");
    const originalBorder = element.style.border;
    element.style.border = "none"; // Hide border for clean image

    html2canvas(element, {
        backgroundColor: null,
        scale: 2 // High quality
    }).then(canvas => {
        element.style.border = originalBorder; // Restore
        const link = document.createElement('a');
        link.download = 'HVM-Rewind-2025.png';
        link.href = canvas.toDataURL("image/png");
        link.click();
    });
}

window.addEventListener('resize', () => { canvas.width = window.innerWidth; canvas.height = window.innerHeight; });