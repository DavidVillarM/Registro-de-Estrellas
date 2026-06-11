import React from 'react'
import ReactDOM from 'react-dom/client'
import App from "./ui/App";
import './styles.css'
import './index.css'


const mount = document.getElementById('estrellas-nw-root') || document.getElementById('root')
if (mount) {
  ReactDOM.createRoot(mount).render(
    <React.StrictMode>
      <App />
    </React.StrictMode>
  )
}
