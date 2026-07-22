import { Controller } from '@hotwired/stimulus';

/*
 * Lets the file input behind it be filled either by dragging an image onto
 * the circle or by clicking it (native label+input behavior), and swaps in
 * a live preview either way.
 */
export default class extends Controller {
    static targets = ['dropzone', 'input', 'preview'];

    dragOver(event) {
        event.preventDefault();
        this.dropzoneTarget.classList.add('border-indigo-500');
    }

    dragLeave() {
        this.dropzoneTarget.classList.remove('border-indigo-500');
    }

    drop(event) {
        event.preventDefault();
        this.dropzoneTarget.classList.remove('border-indigo-500');

        const file = event.dataTransfer.files[0];
        if (!file) {
            return;
        }

        this.inputTarget.files = event.dataTransfer.files;
        this.updatePreview(file);
    }

    change() {
        const file = this.inputTarget.files[0];
        if (file) {
            this.updatePreview(file);
        }
    }

    updatePreview(file) {
        if (!file.type.startsWith('image/')) {
            return;
        }

        this.previewTarget.src = URL.createObjectURL(file);
    }
}
