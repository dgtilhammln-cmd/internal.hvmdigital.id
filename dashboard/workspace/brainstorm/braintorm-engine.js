/**
 * HVM WorkSpace - Brainstorming Engine
 * Version 3.5 - Full Logic Implementation
 */

let canvas;
let currentTool = 'select';
let isPanning = false;
let lastPosX, lastPosY;

// --- 1. INITIALIZATION ---
document.addEventListener('DOMContentLoaded', () => {
    // Inisialisasi Fabric Canvas
    canvas = new fabric.Canvas('mainCanvas', {
        width: window.innerWidth - 260, // Sesuaikan dengan lebar sidebar
        height: window.innerHeight,
        isDrawingMode: false,
        stopContextMenu: true, // Biarkan kita menangani klik kanan sendiri
        backgroundColor: 'transparent'
    });

    // Sesuaikan ukuran saat window di-resize
    window.addEventListener('resize', () => {
        canvas.setDimensions({
            width: window.innerWidth - 260,
            height: window.innerHeight
        });
    });

    // Setup Brush Default
    canvas.freeDrawingBrush = new fabric.PencilBrush(canvas);
    canvas.freeDrawingBrush.width = 4;
    canvas.freeDrawingBrush.color = '#a1ff5a';

    // --- 2. INFINITE CANVAS LOGIC (PAN & ZOOM) ---

    // Zoom dengan Mouse Wheel
    canvas.on('mouse:wheel', function(opt) {
        let delta = opt.e.deltaY;
        let zoom = canvas.getZoom();
        zoom *= 0.999 ** delta;
        if (zoom > 10) zoom = 10;
        if (zoom < 0.1) zoom = 0.1;
        canvas.zoomToPoint({ x: opt.e.offsetX, y: opt.e.offsetY }, zoom);
        updateZoomDisplay();
        opt.e.preventDefault();
        opt.e.stopPropagation();
    });

    // Panning (Geser Layar)
    canvas.on('mouse:down', function(opt) {
        const evt = opt.e;
        // Klik kanan untuk Context Menu
        if (opt.button === 3) {
            handleRightClick(opt);
            return;
        }

        // Alt + Klik atau Drag area kosong untuk geser
        if (evt.altKey === true || (!opt.target && currentTool === 'select')) {
            isPanning = true;
            canvas.selection = false;
            lastPosX = evt.clientX;
            lastPosY = evt.clientY;
        }
        hideContextMenu();
    });

    canvas.on('mouse:move', function(opt) {
        if (isPanning && opt.e) {
            let vpt = this.viewportTransform;
            vpt[4] += opt.e.clientX - lastPosX;
            vpt[5] += opt.e.clientY - lastPosY;
            this.requestRenderAll();
            lastPosX = opt.e.clientX;
            lastPosY = opt.e.clientY;
        }
    });

    canvas.on('mouse:up', function() {
        this.setViewportTransform(this.viewportTransform);
        isPanning = false;
        canvas.selection = true;
    });

    // --- 3. MOODBOARD / IMAGE UPLOAD ---
    document.getElementById('img-upload').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(f) {
            fabric.Image.fromURL(f.target.result, (img) => {
                img.scaleToWidth(300);
                canvas.add(img).setActiveObject(img);
            });
        };
        reader.readAsDataURL(file);
    });

    updateZoomDisplay();
});

// --- 4. TOOLBOX FUNCTIONS ---

function changeTool(tool) {
    currentTool = tool;
    document.querySelectorAll('.t-btn').forEach(btn => btn.classList.remove('active'));
    
    if (tool === 'draw') {
        canvas.isDrawingMode = true;
        document.getElementById('tool-draw').classList.add('active');
    } else {
        canvas.isDrawingMode = false;
        document.getElementById('tool-select').classList.add('active');
    }
}

function clearCanvas() {
    Swal.fire({
        title: 'Reset Canvas?',
        text: "Semua ide di papan akan dihapus permanen!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ff5a5a',
        confirmButtonText: 'Ya, Bersihkan!'
    }).then((result) => {
        if (result.isConfirmed) canvas.clear();
    });
}

// --- 5. ELEMENTS CREATION ---

function addSticky() {
    const rect = new fabric.Rect({
        width: 200, height: 200,
        fill: '#ffee58',
        shadow: 'rgba(0,0,0,0.4) 5px 5px 15px'
    });
    const text = new fabric.Textbox('Ketik ide...', {
        width: 180, fontSize: 18,
        textAlign: 'center', top: 40, left: 10,
        fontFamily: 'Montserrat'
    });
    const group = new fabric.Group([rect, text], { left: 100, top: 100 });
    canvas.add(group).setActiveObject(group);
}

function addNode(title = 'New Topic', color = '#a1ff5a') {
    const circle = new fabric.Circle({
        radius: 60,
        fill: 'rgba(10,10,10,0.8)',
        stroke: color,
        strokeWidth: 3,
        shadow: color + ' 0px 0px 15px'
    });
    const text = new fabric.Textbox(title, {
        width: 100, fontSize: 14,
        textAlign: 'center', top: 45, left: 10,
        fill: '#fff', fontWeight: 'bold',
        fontFamily: 'Montserrat'
    });
    const group = new fabric.Group([circle, text], { left: 250, top: 250 });
    canvas.add(group).setActiveObject(group);
}

function addShape(type) {
    let shape;
    if (type === 'rect') {
        shape = new fabric.Rect({ width: 100, height: 100, fill: 'transparent', stroke: '#fff', strokeWidth: 2 });
    } else {
        shape = new fabric.Circle({ radius: 50, fill: 'transparent', stroke: '#fff', strokeWidth: 2 });
    }
    canvas.add(shape).setActiveObject(shape);
}

// --- 6. NEBULA AI INTEGRATION ---

async function askNebulaBrain() {
    const { value: topic } = await Swal.fire({
        title: 'Nebula Brainstorm',
        input: 'text',
        inputLabel: 'Masukkan topik atau kata kunci utama:',
        inputPlaceholder: 'Contoh: Strategi Marketing Q1',
        showCancelButton: true
    });

    if (topic) {
        Swal.fire({
            title: 'Connecting to Nebula...',
            didOpen: () => Swal.showLoading(),
            allowOutsideClick: false
        });

        try {
            const response = await fetch('../ai-handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    message: `Berikan 5 cabang ide singkat (1-3 kata) untuk topik brainstorming ini: ${topic}. Pisahkan hasilnya hanya dengan koma saja tanpa penjelasan.` 
                })
            });
            const data = await response.json();
            const ideas = data.content.split(',');

            // Tambahkan ide ke canvas secara menyebar
            ideas.forEach((idea, i) => {
                setTimeout(() => {
                    addNode(idea.trim(), '#4efdc4');
                }, i * 300);
            });
            Swal.close();
        } catch (e) {
            Swal.fire('Error', 'Neural link terputus.', 'error');
        }
    }
}

// --- 7. CONTEXT MENU & PROJECT CONVERSION ---

function handleRightClick(opt) {
    if (opt.target) {
        canvas.setActiveObject(opt.target);
        const menu = document.getElementById('contextMenu');
        menu.style.display = 'block';
        menu.style.left = opt.e.clientX + 'px';
        menu.style.top = opt.e.clientY + 'px';
    }
}

function hideContextMenu() {
    document.getElementById('contextMenu').style.display = 'none';
}

function deleteSelected() {
    const active = canvas.getActiveObjects();
    if (active) {
        active.forEach(obj => canvas.remove(obj));
        canvas.discardActiveObject().requestRenderAll();
        hideContextMenu();
    }
}

function convertToProject(target) {
    const obj = canvas.getActiveObject();
    let content = "Untitled Brainstorm Task";
    
    // Ambil teks dari group atau textbox
    if (obj.type === 'group') {
        content = obj._objects.find(o => o.text)?.text || content;
    } else if (obj.text) {
        content = obj.text;
    }

    Swal.fire({
        title: 'Convert Success',
        text: `"${content}" telah dikirim ke ${target.toUpperCase()}`,
        icon: 'success'
    });
    hideContextMenu();
}

// --- 8. TEMPLATES LOGIC ---

function openTemplates() { document.getElementById('tplModal').classList.add('active'); }
function closeTpl() { document.getElementById('tplModal').classList.remove('active'); }

function loadTemplate(type) {
    canvas.clear();
    if (type === 'swot') {
        addNode('STRENGTHS', '#a1ff5a');
        addNode('WEAKNESSES', '#ff5a5a');
        addNode('OPPORTUNITIES', '#4efdc4');
        addNode('THREATS', '#ff9f43');
    } else if (type === 'funnel') {
        addNode('AWARENESS', '#fff');
        addNode('CONSIDERATION', '#fff');
        addNode('CONVERSION', '#fff');
    }
    closeTpl();
}

// --- 9. UTILS ---

function zoomCanvas(factor) {
    let zoom = canvas.getZoom() * factor;
    if (zoom > 10) zoom = 10;
    if (zoom < 0.1) zoom = 0.1;
    canvas.setZoom(zoom);
    updateZoomDisplay();
}

function updateZoomDisplay() {
    document.getElementById('zoomLevel').innerText = Math.round(canvas.getZoom() * 100) + '%';
}

function exportCanvas() {
    const dataURL = canvas.toDataURL({
        format: 'png',
        quality: 1,
        multiplier: 2 // Export HD
    });
    const link = document.createElement('a');
    link.download = 'HVM-Brainstorm-' + Date.now() + '.png';
    link.href = dataURL;
    link.click();
}