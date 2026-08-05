// Rejestracja Service Workera (PWA - cache statyki, offline fallback).
// Świadomie ścieżka absolutna '/sw.js' (nie url()/BASE_URL) - z tego samego
// powodu co import main.js w footer.php: rejestracja SW musi trafić w origin,
// z którego strona jest faktycznie serwowana.
export function registerServiceWorker() {
  if (!('serviceWorker' in navigator)) {
    return;
  }

  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js').catch((error) => {
      console.error('Zdajnik: rejestracja Service Workera nie powiodła się', error);
    });
  });
}
