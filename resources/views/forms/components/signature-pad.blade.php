<div x-data="signaturePad()" x-init="initPad">
    <canvas x-ref="canvas" width="400" height="200" class="border border-gray-300 rounded"></canvas>

    <input
        type="hidden"
        wire:model.defer="{{ $getStatePath() }}"
        x-model="output"
    >

    <button type="button" x-on:click="clearPad()" class="px-3 py-1 bg-red-500 text-white rounded mt-2">Clear</button>
</div>

<script>
    function signaturePad() {
        return {
            canvas: null,
            context: null,
            drawing: false,
            output: '',

            initPad() {
                this.canvas = this.$refs.canvas;
                this.context = this.canvas.getContext('2d');

                this.canvas.addEventListener('mousedown', () => this.drawing = true);
                this.canvas.addEventListener('mouseup', () => {
                    this.drawing = false;
                    this.context.beginPath();
                    this.output = this.canvas.toDataURL("image/png");
                });
                this.canvas.addEventListener('mousemove', this.draw.bind(this));

                // Load state if exists
                @if ($state)
                    let image = new Image();
                    image.src = @json($state);
                    image.onload = () => {
                        this.context.drawImage(image, 0, 0);
                        this.output = image.src;
                    }
                @endif
            },

            draw(e) {
                if (!this.drawing) return;
                const rect = this.canvas.getBoundingClientRect();

                this.context.lineWidth = 2;
                this.context.lineCap = 'round';
                this.context.strokeStyle = '#000000';

                this.context.lineTo(e.clientX - rect.left, e.clientY - rect.top);
                this.context.stroke();
                this.context.beginPath();
                this.context.moveTo(e.clientX - rect.left, e.clientY - rect.top);
            },

            clearPad() {
                this.context.clearRect(0, 0, this.canvas.width, this.canvas.height);
                this.output = '';
            }
        };
    }
</script>
