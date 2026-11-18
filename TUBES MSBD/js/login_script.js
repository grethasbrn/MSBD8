// STREAMING_CHUNK:Initializing event listeners and role switching logic...
document.addEventListener('DOMContentLoaded', function() {
let selectedRole = 'owner'; // Default pilihan adalah Owner

// Mengambil semua elemen kartu pilihan peran
const roleOptions = document.querySelectorAll('.role-option');
const btnSubmit = document.querySelector('.btn-submit') || document.querySelector('button[type="submit"]');

// Menambahkan efek klik dan menyimpan peran yang dipilih
roleOptions.forEach((option, index) => {
    option.addEventListener('click', function() {
        // Hapus kelas 'selected' atau styling aktif dari semua opsi
        roleOptions.forEach(opt => opt.classList.remove('active', 'selected'));
        
        // Tambahkan kelas aktif pada opsi yang diklik
        this.classList.add('active', 'selected');

        // Menentukan peran berdasarkan urutan/elemen
        switch(index) {
            case 0: selectedRole = 'owner'; break;
            case 1: selectedRole = 'korlap'; break;
            case 2: selectedRole = 'helper'; break;
            case 3: selectedRole = 'penari'; break;
            case 4: selectedRole = 'pemusik'; break;
            default: selectedRole = 'owner';
        }
    });
});

// STREAMING_CHUNK:Handling form submission and navigation
if (btnSubmit) {
    btnSubmit.addEventListener('click', function(e) {
        e.preventDefault(); // Mencegah reload form bawaan browser

        // Navigasi ke halaman sesuai dengan peran yang dipilih
        if (selectedRole === 'owner') {
            window.location.href = 'dashboard_owner.html';
        } else if (selectedRole === 'korlap') {
            window.location.href = 'dashboard_korlap.html';
        } else if (selectedRole === 'helper') {
            window.location.href = 'dashboard_helper.html';
        } else if (selectedRole === 'penari') {
            window.location.href = 'dashboard_penari.html';
        } else if (selectedRole === 'pemusik') {
            window.location.href = 'dashboard_pemusik.html';
        } else {
            window.location.href = 'dashboard_owner.html';
        }
    });
}


});