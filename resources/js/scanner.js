// The door scanner's camera and QR decoding (html5-qrcode, about 109 KB gzipped). Only the scanner view loads this
// entry (@assets in livewire/ops/scanner-terminal); every other page, the event page included, never downloads it.
// The scanner waits for window.Html5Qrcode ("Camera engine loading...") until this module has run.
import { Html5Qrcode } from 'html5-qrcode';

window.Html5Qrcode = Html5Qrcode;
