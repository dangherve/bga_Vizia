class ImageAnimation {
    constructor(options = {}) {
        this.imagesSrc = options.images || [];
        this.duration = options.duration || 5000;
        this.count = options.count || 40;
        // Modes:
        // "rain" | "top" | "bottom" | "left" | "right"
        // "top-left" | "top-right" | "bottom-left" | "bottom-right"
        // "edges" | "explosion"
        this.mode = options.mode || "rain";

        this.origin = options.origin || null;
        this.nbOrigin = options.nbOrigin || 1;
        this.originCenter = options.originCenter ?? true;

        // ⏱ spawn control (time-based)
        this.spawnInterval = options.spawnInterval || 30;
        this.lastSpawnTime = 0;
        this.spawned = 0;

        this.canvas = null;
        this.ctx = null;
        this.drops = [];
        this.images = [];
        this.running = false;
        this.animationId = null;

        this._resizeHandler = this.resize.bind(this);
    }

    // ----------------------------
    // IMAGE LOADING
    // ----------------------------
    async preload() {
        if (this.images.length) return;

        const promises = this.imagesSrc.map(src => {
            if (typeof src === "string" && src.trim().startsWith("<svg")) {
                return new Promise((resolve, reject) => {
                    const blob = new Blob([src], { type: "image/svg+xml" });
                    const url = URL.createObjectURL(blob);

                    const img = new Image();
                    img.onload = () => {
                        URL.revokeObjectURL(url);
                        resolve(img);
                    };
                    img.onerror = reject;
                    img.src = url;
                });
            }

            return new Promise((resolve, reject) => {
                const img = new Image();
                img.onload = () => resolve(img);
                img.onerror = reject;
                img.src = src;
            });
        });

        this.images = await Promise.all(promises);
    }

    // ----------------------------
    // CANVAS
    // ----------------------------
    createCanvas() {
        if (this.canvas) return;

        this.canvas = document.createElement("canvas");
        Object.assign(this.canvas.style, {
            position: "fixed",
            top: 0,
            left: 0,
            pointerEvents: "none",
            zIndex: 9999
        });

        document.body.appendChild(this.canvas);
        this.ctx = this.canvas.getContext("2d");

        this.resize();
        window.addEventListener("resize", this._resizeHandler);

        if (this.mode === "explosion" && !this.origin && this.nbOrigin > 1) {
            this.origin = this.originCenter
                ? this.generateOrigins(this.nbOrigin)
                : this.generateRandomOrigins(this.nbOrigin);
        }
    }

    resize() {
        if (!this.canvas) return;
        this.canvas.width = window.innerWidth;
        this.canvas.height = window.innerHeight;
    }

    // ----------------------------
    // ORIGINS
    // ----------------------------
    getOrigin() {
        if (!this.origin) {
            if(this.originCenter)
                return {
                    x: this.canvas.width / 2,
                    y: this.canvas.height / 2
                };
            else{
                if (this.xRand === undefined){
                    this.xRand = Math.random() * this.canvas.width;
                    this.yRand = Math.random() * this.canvas.height;
                 }

                return {
                    x: this.xRand,
                    y: this.yRand
                };
            }

        }

        if (Array.isArray(this.origin)) {
            return this.origin[Math.floor(Math.random() * this.origin.length)];
        }

        return this.origin;
    }

    generateRandomOrigins(count) {
        return Array.from({ length: count }, () => ({
            x: Math.random() * this.canvas.width,
            y: Math.random() * this.canvas.height
        }));
    }

    generateOrigins(count) {
        const origins = [];
        const cols = Math.ceil(Math.sqrt(count));
        const w = this.canvas.width / cols;
        const h = this.canvas.height / cols;

        for (let i = 0; i < count; i++) {
            origins.push({
                x: (i % cols + 0.5) * w,
                y: (Math.floor(i / cols) + 0.5) * h
            });
        }
        return origins;
    }

    // ----------------------------
    // HELPERS (NEW)
    // ----------------------------
    randomImage() {
        return this.images[Math.floor(Math.random() * this.images.length)];
    }

    randomSpeed() {
        return 2 + Math.random() * 3;
    }

    addNoise(v) {
        return v + (Math.random() - 0.5) * 0.5;
    }

    // spawn from an edge
    spawnFromEdge(edge) {
        const w = this.canvas.width;
        const h = this.canvas.height;

        switch (edge) {
            case "top":
                return { x: Math.random() * w, y: -20 };
            case "bottom":
                return { x: Math.random() * w, y: h + 20 };
            case "left":
                return { x: -20, y: Math.random() * h };
            case "right":
                return { x: w + 20, y: Math.random() * h };
        }
    }

    // velocity from angle
    velocityFromAngle(angle, speed) {
        return {
            vx: this.addNoise(Math.cos(angle) * speed),
            vy: this.addNoise(Math.sin(angle) * speed)
        };
    }

    // ----------------------------
    // PARTICLES
    // ----------------------------
    createDrop() {
        const img = this.randomImage();
        const speed = this.randomSpeed();

        let x, y, vx, vy;

        switch (this.mode) {
            case "top":
            case "rain": {
                ({ x, y } = this.spawnFromEdge("top"));
                ({ vx, vy } = this.velocityFromAngle(Math.PI / 2, speed));
                break;
            }

            case "bottom": {
                ({ x, y } = this.spawnFromEdge("bottom"));
                ({ vx, vy } = this.velocityFromAngle(-Math.PI / 2, speed));
                break;
            }

            case "left": {
                ({ x, y } = this.spawnFromEdge("left"));
                ({ vx, vy } = this.velocityFromAngle(0, speed));
                break;
            }

            case "right": {
                ({ x, y } = this.spawnFromEdge("right"));
                ({ vx, vy } = this.velocityFromAngle(Math.PI, speed));
                break;
            }

            // diagonals
            case "top-left": {
                ({ x, y } = this.spawnFromEdge(Math.random() < 0.5 ? "top" : "left"));
                ({ vx, vy } = this.velocityFromAngle(Math.PI / 4, speed));
                break;
            }

            case "top-right": {
                ({ x, y } = this.spawnFromEdge(Math.random() < 0.5 ? "top" : "right"));
                ({ vx, vy } = this.velocityFromAngle((3 * Math.PI) / 4, speed));
                break;
            }

            case "bottom-left": {
                ({ x, y } = this.spawnFromEdge(Math.random() < 0.5 ? "bottom" : "left"));
                ({ vx, vy } = this.velocityFromAngle((-Math.PI) / 4, speed));
                break;
            }

            case "bottom-right": {
                ({ x, y } = this.spawnFromEdge(Math.random() < 0.5 ? "bottom" : "right"));
                ({ vx, vy } = this.velocityFromAngle((-3 * Math.PI) / 4, speed));
                break;
            }

            case "edges": {
                const edges = ["top", "bottom", "left", "right"];
                const edge = edges[Math.floor(Math.random() * edges.length)];
                ({ x, y } = this.spawnFromEdge(edge));

                const angle = Math.random() * Math.PI * 2;
                ({ vx, vy } = this.velocityFromAngle(angle, speed));
                break;
            }

            case "explosion": {
                const o = this.getOrigin();
                const angle = Math.random() * Math.PI * 2;
                ({ vx, vy } = this.velocityFromAngle(angle, speed));

                x = o.x;
                y = o.y;
                break;
            }
        }

        return {
            img,
            x,
            y,
            vx,
            vy,
            size: 30 + Math.random() * 40,
            rotation: Math.random() * Math.PI,
            vr: (Math.random() - 0.5) * 0.1
        };
    }

    initDrops() {
        this.drops = [];
        this.spawned = 0;
    }

    // ----------------------------
    // ANIMATION
    // ----------------------------
    animate() {
        if (!this.running) return;

        const now = performance.now();

        // progressive spawn
        if (this.spawned < this.count && now - this.lastSpawnTime > this.spawnInterval) {
            this.drops.push(this.createDrop());
            this.spawned++;
            this.lastSpawnTime = now;
        }

        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

        for (const drop of this.drops) {
            drop.x += drop.vx;
            drop.y += drop.vy;
            drop.rotation += drop.vr;

            this.ctx.save();
            this.ctx.translate(drop.x, drop.y);
            this.ctx.rotate(drop.rotation);
            this.ctx.drawImage(drop.img, -drop.size / 2, -drop.size / 2, drop.size, drop.size);
            this.ctx.restore();

            if (
                drop.x < -100 || drop.x > this.canvas.width + 100 ||
                drop.y < -100 || drop.y > this.canvas.height + 100
            ) {
                Object.assign(drop, this.createDrop());
            }
        }

        this.animationId = requestAnimationFrame(() => this.animate());
    }

    async start(duration = this.duration) {
        if (this.running || !this.imagesSrc.length) return;

        await this.preload();
        this.createCanvas();
        this.initDrops();

        this.running = true;
        this.animate();

        if (duration > 0) {
            setTimeout(() => this.stop(), duration);
        }
    }

    stop() {
        if (!this.running) return;

        this.running = false;
        cancelAnimationFrame(this.animationId);
        window.removeEventListener("resize", this._resizeHandler);

        if (this.canvas) {
            this.canvas.remove();
            this.canvas = null;
        }

        this.ctx = null;
        this.drops = [];
    }
}
