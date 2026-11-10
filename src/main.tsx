import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import "./index.css";
import App from "./App.tsx";
import { AppWrapper } from "./components/common/PageMeta.tsx";


// GLOBAL ERROR HANDLER FOR DEBUGGING
window.onerror = function (message, source, lineno, colno, error) {
  const div = document.createElement('div');
  div.style.color = 'white';
  div.style.backgroundColor = 'red';
  div.style.padding = '20px';
  div.style.position = 'fixed';
  div.style.top = '0';
  div.style.left = '0';
  div.style.width = '100%';
  div.style.zIndex = '9999';
  div.innerHTML = '<h1>Application Error</h1><pre>' + message + '\n' + source + ':' + lineno + '</pre>';
  document.body.appendChild(div);
};

// Auto-migrate legacy hash URLs (e.g. /#/product/slug -> /product/slug)
if (typeof window !== 'undefined' && window.location.hash) {
  const hash = window.location.hash;
  const match = hash.match(/^#\/?(.*)$/);
  if (match && match[1] && match[1] !== '/') {
    const cleanUrl = '/' + match[1];
    window.history.replaceState(null, '', cleanUrl);
  }
}

createRoot(document.getElementById("root")!).render(
  <StrictMode>
    <AppWrapper>
      <App />
    </AppWrapper>
  </StrictMode>
);
