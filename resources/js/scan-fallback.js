// Tahap 3 (Spesifikasi UI/UX 6.8/6.12): pemindai cadangan untuk browser tanpa BarcodeDetector
// (mis. Safari iOS). Dimuat hanya saat dibutuhkan oleh komponen <x-hp.scan-button>.
import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode';

window.DtHtml5Qrcode = Html5Qrcode;
window.DtHtml5QrcodeFormats = Html5QrcodeSupportedFormats;
window.dispatchEvent(new CustomEvent('dt-scan-fallback-ready'));
