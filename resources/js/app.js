import './bootstrap';

// 1. Inisiasi Alpine.js
import Alpine from 'alpinejs';
window.Alpine = Alpine;
Alpine.start();


// --- Fungsionalitas Custom HVT ---

document.addEventListener('DOMContentLoaded', function () {

    // 2. Auto-Focus pada Input AWB Scanning
    const awbInput = document.querySelector('input[name="awb_number"]');
    if (awbInput) {
        awbInput.focus();
    }

    // 3. Mencegah Double Submit pada Form Finalize
    const finalizeForm = document.getElementById('finalize-form');
    if (finalizeForm) {
        finalizeForm.addEventListener('submit', function() {
            // Nonaktifkan tombol submit setelah klik pertama
            const button = finalizeForm.querySelector('button[type="submit"]');

            if (button) {
                button.disabled = true;
                button.innerText = 'Processing... Please Wait';
                // Ubah kelas untuk visual feedback
                button.classList.add('bg-gray-400', 'hover:bg-gray-400');
                button.classList.remove('bg-yellow-400');
            }
        });
    }
});

const setupForm = document.getElementById('setup-form');
const batchStatus = setupForm ? setupForm.querySelector('input[name="handover_id"]').disabled : false;

// Hanya clear form jika tombol start aktif (yaitu mode staged TIDAK aktif)
if (setupForm && !batchStatus) {
    setupForm.reset();
}

/* GENERAL FUNCTION */
$(document).on('click', '.del-iteminfo-button', function(e) {
    event.preventDefault();
    $(this).parent().parent().parent().remove();
});
$('#add-item').click(function(e) {
    event.preventDefault();
    var itemInfo = $('template');
    $('#item-info-container').append(itemInfo.html());
});
$('.ms-auto').click(function(e) {
    e.preventDefault();
    $($(this).data('dismiss-target')).remove();
});
