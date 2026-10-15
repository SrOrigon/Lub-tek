/**
 * LUB-TEK - Client-Side Image Compressor (Chão de Fábrica / PWA)
 * Reduz fotos de celulares/tablets industriais antes de armazenar em IndexedDB ou transmitir via 4G.
 * - Redimensiona proporções para largura/altura máxima de 1280px
 * - Converte para image/webp (qualidade 0.75) com fallback transparente para image/jpeg
 */

(function (window) {
    'use strict';

    const LubtekImageCompressor = {
        MAX_DIMENSION: 1280,
        DEFAULT_QUALITY: 0.75,

        /**
         * Comprime um arquivo de imagem (File ou Blob)
         * @param {File|Blob} file Arquivo original vindo de <input type="file"> ou câmera
         * @param {number} maxDimension Dimensão máxima (largura ou altura) em pixels
         * @param {number} quality Qualidade de compressão (0.1 a 1.0)
         * @returns {Promise<File>} Arquivo comprimido em formato WebP/JPEG
         */
        compress: function (file, maxDimension, quality) {
            maxDimension = maxDimension || this.MAX_DIMENSION;
            quality = quality || this.DEFAULT_QUALITY;

            // Se não for imagem válida, retorna o arquivo original sem quebrar
            if (!file || !file.type || !file.type.startsWith('image/')) {
                return Promise.resolve(file);
            }

            // Não reprocessa SVGs ou GIFs animados
            if (file.type === 'image/svg+xml' || file.type === 'image/gif') {
                return Promise.resolve(file);
            }

            return new Promise(function (resolve) {
                const img = new Image();
                const url = URL.createObjectURL(file);

                img.onload = function () {
                    URL.revokeObjectURL(url);

                    let width = img.naturalWidth || img.width;
                    let height = img.naturalHeight || img.height;

                    // Calcula novas dimensões preservando proporção
                    if (width > maxDimension || height > maxDimension) {
                        if (width > height) {
                            height = Math.round((height * maxDimension) / width);
                            width = maxDimension;
                        } else {
                            width = Math.round((width * maxDimension) / height);
                            height = maxDimension;
                        }
                    }

                    const canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;

                    const ctx = canvas.getContext('2d');
                    if (!ctx) {
                        return resolve(file); // Fallback caso canvas falhe
                    }

                    // Suavização bilinear para qualidade industrial
                    ctx.imageSmoothingEnabled = true;
                    ctx.imageSmoothingQuality = 'high';
                    ctx.drawImage(img, 0, 0, width, height);

                    // Testa suporte a WebP no canvas
                    const targetFormat = 'image/webp';
                    canvas.toBlob(function (blob) {
                        if (!blob) {
                            // Fallback para JPEG caso o browser não suporte WebP no canvas
                            canvas.toBlob(function (fallbackBlob) {
                                if (!fallbackBlob) return resolve(file);
                                const compressedFile = new File([fallbackBlob], (file.name || 'evidencia.jpg').replace(/\.[^.]+$/, '.jpg'), {
                                    type: 'image/jpeg',
                                    lastModified: Date.now()
                                });
                                resolve(compressedFile);
                            }, 'image/jpeg', quality);
                            return;
                        }

                        const ext = blob.type === 'image/webp' ? '.webp' : '.jpg';
                        const baseName = (file.name || 'evidencia').replace(/\.[^.]+$/, '');
                        const compressedFile = new File([blob], baseName + ext, {
                            type: blob.type,
                            lastModified: Date.now()
                        });

                        // Se por algum motivo raro o arquivo comprimido ficou maior que o original, mantém o original
                        if (compressedFile.size > file.size && file.size > 0) {
                            resolve(file);
                        } else {
                            resolve(compressedFile);
                        }
                    }, targetFormat, quality);
                };

                img.onerror = function () {
                    URL.revokeObjectURL(url);
                    resolve(file);
                };

                img.src = url;
            });
        }
    };

    window.LubtekImageCompressor = LubtekImageCompressor;
})(window);
