(function () {
    'use strict';

    var DEFAULT_LANG = 'en';
    var SUPPORTED = ['fr', 'en', 'es'];
    var LOCALE_PATH = window.I18N_LOCALE_PATH || './locales';
    var TRANSLATE_ENDPOINT = window.I18N_TRANSLATE_ENDPOINT || './api/translate.php';

    var currentLang = DEFAULT_LANG;
    var translations = {};
    var localeCache = {};
    var initDone = false;
    var autoTextNodes = new Map();

    function getCookie(name) {
        var match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        return match ? decodeURIComponent(match[2]) : null;
    }

    function setCookie(name, value, days) {
        var expires = '';
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = '; expires=' + date.toUTCString();
        }
        document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=/';
    }

    function normalizeLang(lang) {
        if (!lang) return DEFAULT_LANG;
        lang = String(lang).toLowerCase();
        return SUPPORTED.indexOf(lang) >= 0 ? lang : DEFAULT_LANG;
    }

    function encodeAuto(text) {
        try {
            return btoa(unescape(encodeURIComponent(text)));
        } catch (e) {
            return '';
        }
    }

    function decodeAuto(text) {
        try {
            return decodeURIComponent(escape(atob(text)));
        } catch (e) {
            return '';
        }
    }

    function shouldTranslateText(text) {
        if (!text) return false;
        var trimmed = text.trim();
        if (!trimmed) return false;
        if (/^(https?:\/\/|www\.)/i.test(trimmed)) return false;
        if (/^[\d\s\.,\-\+]+$/.test(trimmed)) return false;
        return /[A-Za-zÀ-ÿ]/.test(trimmed);
    }

    function loadLocale(lang) {
        if (localeCache[lang]) {
            translations = localeCache[lang];
            return Promise.resolve(translations);
        }

        return fetch(LOCALE_PATH + '/' + lang + '.json', { cache: 'no-store' })
            .then(function (res) {
                if (!res.ok) return {};
                return res.json();
            })
            .then(function (data) {
                localeCache[lang] = data || {};
                translations = localeCache[lang];
                return translations;
            })
            .catch(function () {
                translations = {};
                return translations;
            });
    }

    function t(key, fallback) {
        if (translations && Object.prototype.hasOwnProperty.call(translations, key)) {
            return translations[key];
        }
        return fallback || key;
    }

    function format(key, vars, fallback) {
        var template = t(key, fallback);
        if (!vars) return template;
        return template.replace(/\{(\w+)\}/g, function (match, k) {
            return Object.prototype.hasOwnProperty.call(vars, k) ? vars[k] : match;
        });
    }

    function parseAttrMap(raw, defaultKey) {
        var map = [];
        if (!raw) return map;
        var entries = raw.split(',').map(function (item) { return item.trim(); }).filter(Boolean);
        entries.forEach(function (entry) {
            var parts = entry.split(':');
            if (parts.length === 1) {
                map.push({ attr: parts[0], key: defaultKey });
            } else {
                map.push({ attr: parts[0], key: parts.slice(1).join(':') });
            }
        });
        return map;
    }

    function translateTexts(texts, lang) {
        if (!texts.length || lang === DEFAULT_LANG) {
            return Promise.resolve(texts.slice());
        }

        return fetch(TRANSLATE_ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ text: texts, target_lang: lang })
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data || !Array.isArray(data.translations)) return texts.slice();
                return data.translations;
            })
            .catch(function () { return texts.slice(); });
    }

    function collectAutoTextNodes(root) {
        var scope = root || document.body;
        if (!scope) return;
        var walker = document.createTreeWalker(scope, NodeFilter.SHOW_TEXT, {
            acceptNode: function (node) {
                if (!node || !node.parentElement) return NodeFilter.FILTER_REJECT;
                var parent = node.parentElement;
                if (parent.closest('script,style,noscript,code,pre')) return NodeFilter.FILTER_REJECT;
                if (parent.hasAttribute('data-i18n')) return NodeFilter.FILTER_REJECT;
                var text = node.nodeValue || '';
                if (!shouldTranslateText(text)) return NodeFilter.FILTER_REJECT;
                return NodeFilter.FILTER_ACCEPT;
            }
        });

        while (walker.nextNode()) {
            var textNode = walker.currentNode;
            if (!autoTextNodes.has(textNode)) {
                autoTextNodes.set(textNode, textNode.nodeValue || '');
            }
        }
    }

    function tagAutoAttributes(root) {
        var attrTargets = (root || document).querySelectorAll('[placeholder],[title],[aria-label],[value]');
        attrTargets.forEach(function (el) {
            if (el.hasAttribute('data-i18n-attr')) {
                return;
            }
            var attrs = [];
            var placeholder = el.getAttribute('placeholder');
            if (placeholder && shouldTranslateText(placeholder)) {
                attrs.push('placeholder:auto:' + encodeAuto(placeholder));
            }
            var title = el.getAttribute('title');
            if (title && shouldTranslateText(title)) {
                attrs.push('title:auto:' + encodeAuto(title));
            }
            var aria = el.getAttribute('aria-label');
            if (aria && shouldTranslateText(aria)) {
                attrs.push('aria-label:auto:' + encodeAuto(aria));
            }
            var value = el.getAttribute('value');
            var type = (el.getAttribute('type') || '').toLowerCase();
            if (value && (type === 'button' || type === 'submit' || type === 'reset') && shouldTranslateText(value)) {
                attrs.push('value:auto:' + encodeAuto(value));
            }
            if (attrs.length) {
                el.setAttribute('data-i18n-attr', attrs.join(','));
            }
        });
    }

    function applyTranslations(root) {
        var scope = root || document;
        var elements = scope.querySelectorAll('[data-i18n],[data-i18n-attr],[data-i18n-css]');
        var autoQueue = [];

        elements.forEach(function (el) {
            var key = el.getAttribute('data-i18n');
            if (key) {
                if (key.indexOf('auto:') === 0) {
                    autoQueue.push({ el: el, text: decodeAuto(key.slice(5)) });
                } else {
                    el.textContent = t(key, el.textContent);
                }
            }

            var attrRaw = el.getAttribute('data-i18n-attr');
            if (attrRaw) {
                var attrMap = parseAttrMap(attrRaw, key);
                attrMap.forEach(function (item) {
                    if (!item.attr) return;
                    if (!item.key) return;
                    if (item.key.indexOf('auto:') === 0) {
                        autoQueue.push({
                            el: el,
                            text: decodeAuto(item.key.slice(5)),
                            attr: item.attr
                        });
                    } else {
                        el.setAttribute(item.attr, t(item.key, el.getAttribute(item.attr) || ''));
                    }
                });
            }

            var cssKey = el.getAttribute('data-i18n-css');
            if (cssKey) {
                if (cssKey.indexOf('auto:') === 0) {
                    autoQueue.push({
                        el: el,
                        text: decodeAuto(cssKey.slice(5)),
                        css: true
                    });
                } else {
                    el.setAttribute('data-label', t(cssKey, el.getAttribute('data-label') || ''));
                }
            }
        });

        autoTextNodes.forEach(function (text, node) {
            if (!node.isConnected) {
                autoTextNodes.delete(node);
                return;
            }
            autoQueue.push({ node: node, text: text });
        });

        if (currentLang === DEFAULT_LANG) {
            autoTextNodes.forEach(function (text, node) {
                if (!node.isConnected) {
                    autoTextNodes.delete(node);
                    return;
                }
                node.nodeValue = text;
            });
            return Promise.resolve();
        }

        if (!autoQueue.length) {
            return Promise.resolve();
        }

        var uniqueTexts = [];
        var lookup = {};
        autoQueue.forEach(function (item) {
            var text = item.text || '';
            if (!text) return;
            if (!lookup[text]) {
                lookup[text] = [];
                uniqueTexts.push(text);
            }
            lookup[text].push(item);
        });

        return translateTexts(uniqueTexts, currentLang).then(function (translated) {
            translated.forEach(function (value, idx) {
                var original = uniqueTexts[idx];
                var items = lookup[original] || [];
                items.forEach(function (item) {
                    if (item.attr) {
                        item.el.setAttribute(item.attr, value);
                    } else if (item.css) {
                        item.el.setAttribute('data-label', value);
                    } else if (item.node) {
                        item.node.nodeValue = value;
                    } else {
                        item.el.textContent = value;
                    }
                });
            });
        });
    }

    function setLang(lang) {
        currentLang = normalizeLang(lang);
        localStorage.setItem('lang', currentLang);
        setCookie('lang', currentLang, 365);
        document.documentElement.lang = currentLang;
        return loadLocale(currentLang).then(function () {
            return applyTranslations(document);
        });
    }

    function init() {
        if (initDone) return;
        initDone = true;
        currentLang = normalizeLang(localStorage.getItem('lang') || getCookie('lang') || DEFAULT_LANG);
        document.documentElement.lang = currentLang;

        collectAutoTextNodes(document.body);
        tagAutoAttributes(document.body);
        loadLocale(currentLang).then(function () {
            applyTranslations(document);
        });

        var langSelect = document.getElementById('langSelect');
        if (langSelect) {
            langSelect.value = currentLang;
            langSelect.addEventListener('change', function (e) {
                setLang(e.target.value);
            });
        }

        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (!(node instanceof HTMLElement)) return;
                    collectAutoTextNodes(node);
                    tagAutoAttributes(node);
                    applyTranslations(node);
                });
            });
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

    window.I18N = {
        t: t,
        format: format,
        setLang: setLang,
        getLang: function () { return currentLang; },
        apply: function () { return applyTranslations(document); }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
