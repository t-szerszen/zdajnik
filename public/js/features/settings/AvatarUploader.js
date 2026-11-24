/**
 * Moduł odpowiedzialny za przesyłanie awatarów.
 * Wykorzystuje Canvas API do Client-Side Image Compression.
 */
export default class AvatarUploader {
  constructor() {
    this.container = document.getElementById('avatar-uploader');
    if (!this.container) return;

    this.fileInput = document.getElementById('avatar-file-input');
    this.previewImg = document.getElementById('avatar-preview-img');
    this.uploadBtn = document.getElementById('avatar-upload-btn');
    this.defaultAvatars = document.querySelectorAll('.default-avatar-btn');
    this.loader = document.getElementById('avatar-loader');

    this.maxSize = 256; // Max szerokość/wysokość do jakiej kompresujemy
    this.apiUrl = '/api/user/avatar';

    this.initEvents();
  }

  initEvents() {
    // Kliknięcie w przycisk "Wgraj własne zdjęcie"
    this.uploadBtn.addEventListener('click', () => {
      this.fileInput.click();
    });

    // Zmiana pliku w input
    this.fileInput.addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (file) {
        this.processAndUploadImage(file);
      }
    });

    // Wybór domyślnego awatara
    this.defaultAvatars.forEach(btn => {
      btn.addEventListener('click', (e) => {
        const src = e.currentTarget.dataset.src;
        this.uploadDefaultAvatar(src);
      });
    });
  }

  setLoading(isLoading) {
    if (isLoading) {
      this.loader.classList.add('is-active');
      this.container.classList.add('is-loading');
    } else {
      this.loader.classList.remove('is-active');
      this.container.classList.remove('is-loading');
    }
  }

  async processAndUploadImage(file) {
    if (!file.type.startsWith('image/')) {
      alert('Wybrany plik nie jest obrazem.');
      return;
    }

    this.setLoading(true);

    try {
      const base64Data = await this.compressImage(file);
      await this.sendToServer({
        type: 'custom',
        avatar: base64Data
      });
    } catch (err) {
      console.error(err);
      alert('Wystąpił błąd podczas kompresji lub przesyłania zdjęcia.');
    } finally {
      this.setLoading(false);
      this.fileInput.value = ''; // Reset input
    }
  }

  async uploadDefaultAvatar(src) {
    this.setLoading(true);
    try {
      await this.sendToServer({
        type: 'default',
        avatar: src
      });
    } catch (err) {
      console.error(err);
      alert('Wystąpił błąd podczas ustawiania domyślnego awatara.');
    } finally {
      this.setLoading(false);
    }
  }

  async sendToServer(payload) {
    const response = await fetch(this.apiUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
      },
      body: JSON.stringify(payload)
    });

    const data = await response.json();

    if (data.success) {
      // Zaktualizuj obrazki na stronie
      const newPath = '/images/avatars/' + data.avatar_path + '?v=' + Date.now();

      // Podgląd w ustawieniach
      if (this.previewImg) {
        this.previewImg.src = newPath;
      }

      // Ikona w nawigacji
      const navAvatar = document.querySelector('.navbar__avatar');
      if (navAvatar) {
        navAvatar.src = newPath;
      } else {
        // Jeśli wcześniej nie było awatara, trzeba zamienić ikonę na <img>
        const navIcon = document.querySelector('.navbar__user-icon');
        if (navIcon) {
          const img = document.createElement('img');
          img.src = newPath;
          img.className = 'navbar__avatar';
          img.alt = 'Twój Avatar';
          navIcon.parentNode.replaceChild(img, navIcon);
        }
      }
    } else {
      throw new Error(data.message || 'Błąd serwera');
    }
  }

  compressImage(file) {
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = (event) => {
        const img = new Image();
        img.onload = () => {
          const canvas = document.createElement('canvas');
          let width = img.width;
          let height = img.height;

          // Obliczamy proporcje, aby przyciąć do kwadratu (Crop to Square)
          const size = Math.min(width, height);
          const sourceX = (width - size) / 2;
          const sourceY = (height - size) / 2;

          // Docelowy rozmiar
          const targetSize = Math.min(size, this.maxSize);
          canvas.width = targetSize;
          canvas.height = targetSize;

          const ctx = canvas.getContext('2d');

          // Wygładzanie obrazu
          ctx.imageSmoothingEnabled = true;
          ctx.imageSmoothingQuality = 'high';

          // Rysujemy wykadrowany obraz na canvasie
          ctx.drawImage(img, sourceX, sourceY, size, size, 0, 0, targetSize, targetSize);

          // Konwersja na WebP z kompresją 0.8
          const dataUrl = canvas.toDataURL('image/webp', 0.8);
          resolve(dataUrl);
        };
        img.onerror = (err) => reject(err);
        img.src = event.target.result;
      };
      reader.onerror = (err) => reject(err);
      reader.readAsDataURL(file);
    });
  }
}
