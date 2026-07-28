// Configuracion compartida para los formularios que necesitan un backend
// (Sumate y Form Kickoff). Astro es estatico y no puede procesar envios de
// formularios por si solo, asi que usamos Web3Forms (https://web3forms.com)
// como backend: recibe el POST, adjunta archivos y reenvia todo por mail.
//
// COMO ACTIVARLO:
// 1. Entrar a https://web3forms.com/ y crear una Access Key gratis con el
//    mail donde querés recibir los envios (ej. info@borregastudio.com).
// 2. Pegar esa key aca abajo, reemplazando el placeholder.
//
// La Access Key de Web3Forms esta pensada para exponerse en el frontend
// (no es un secreto como una API key tradicional): así lo indica su propia
// documentacion, por eso vive en un archivo de codigo y no en variables
// de entorno.
export const WEB3FORMS_ACCESS_KEY = 'REEMPLAZAR_CON_TU_ACCESS_KEY_DE_WEB3FORMS';

export const WEB3FORMS_ENDPOINT = 'https://api.web3forms.com/submit';
