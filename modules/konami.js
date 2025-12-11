class KonamiCode {
  constructor(callback) {
    this.konamiSequence = [38, 38, 40, 40, 37, 39, 37, 39, 66, 65];
    this.currentIndex = 0;
    this.callback = callback;
    this.handleKeyDown = this.handleKeyDown.bind(this);

    document.addEventListener('keydown', this.handleKeyDown);
  }

  handleKeyDown(event) {
    const key = event.keyCode;

    if (key === this.konamiSequence[this.currentIndex]) {
      this.currentIndex++;
      if (this.currentIndex === this.konamiSequence.length) {
        this.callback(); // Trigger the Konami code effect
        this.currentIndex = 0;
      }
    } else {
      this.currentIndex = 0;
    }
  }

  destroy() {
    document.removeEventListener('keydown', this.handleKeyDown);
  }
}


