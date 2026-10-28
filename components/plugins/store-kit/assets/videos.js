// Click-to-play in a full-screen player: the TikTok iframe loads only when asked.
// Playback is driven through TikTok's postMessage API (developers.tiktok.com/docs/en/embed-player):
// on `onPlayerReady` we send `mute` + `play` (browsers only allow muted autoplay; without it the player
// sits on a black screen, especially on phones), and the "Bật tiếng" button sends `unMute`.
// The `muted=1` URL parameter is deliberately not used: it locks the volume at 0.
// Some posts never initialise inside the embed (no `onPlayerReady`, even the player's own play button does nothing);
// after READY_TIMEOUT the dialog shows a message pointing to "Mở trên TikTok".
// Without <dialog> support the poster link simply opens the video on TikTok.
(() => {
  if (typeof HTMLDialogElement !== "function") return;

  const ORIGIN = "https://www.tiktok.com";
  const READY_TIMEOUT = 8000;
  let dialog, frame, readyTimer, muted = true;
  const showError = () => dialog && (dialog.querySelector(".sk-player__error").hidden = false);

  const send = (type) => frame && frame.contentWindow && frame.contentWindow.postMessage({ type, value: null, "x-tiktok-player": true }, ORIGIN);

  function setMuted(state) {
    muted = state;
    const btn = dialog.querySelector(".sk-player__sound");
    btn.setAttribute("aria-pressed", String(!state));
    btn.querySelector("span").textContent = state ? "Bật tiếng" : "Tắt tiếng";
  }

  function getDialog() {
    if (dialog) return dialog;
    dialog = document.createElement("dialog");
    dialog.className = "sk-player";
    dialog.setAttribute("aria-label", "Video TikTok");
    dialog.innerHTML =
      '<button type="button" class="sk-player__close" aria-label="Đóng video">' +
      '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></button>' +
      '<div class="sk-player__stage"><div class="sk-player__frame"></div>' +
      '<p class="sk-player__error" hidden>Video không phát được ở đây. Bấm "Mở trên TikTok" để xem.</p>' +
      '<div class="sk-player__bar">' +
      '<button type="button" class="sk-player__sound" aria-pressed="false">' +
      '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9h4l5-4v14l-5-4H4z"/><path d="M16 9a4 4 0 0 1 0 6M18.5 6.5a8 8 0 0 1 0 11"/></svg><span>Bật tiếng</span></button>' +
      '<a class="sk-player__open" target="_blank" rel="noopener">Mở trên TikTok</a></div></div>';

    dialog.addEventListener("click", (e) => {
      if (e.target === dialog || e.target.classList.contains("sk-player__stage") || e.target.closest(".sk-player__close")) dialog.close();
      if (e.target.closest(".sk-player__sound")) {
        send(muted ? "unMute" : "mute");
        setMuted(!muted);
      }
    });
    dialog.addEventListener("close", () => {
      dialog.querySelector(".sk-player__frame").replaceChildren();
      frame = null;
      clearTimeout(readyTimer);
      document.documentElement.classList.remove("sk-player-open");
    });
    window.addEventListener("message", (e) => {
      if (e.origin !== ORIGIN || !frame || e.source !== frame.contentWindow || !e.data || !e.data["x-tiktok-player"]) return;
      if (e.data.type === "onPlayerReady") {
        clearTimeout(readyTimer);
        send("mute");
        send("play");
      } else if (e.data.type === "onMute") {
        setMuted(Boolean(e.data.value));
      } else if (e.data.type === "onPlayerError") {
        showError();
      }
    });
    document.body.append(dialog);
    return dialog;
  }

  document.addEventListener("click", (e) => {
    const link = e.target.closest("a[data-sk-video]");
    if (!link || e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) return;
    e.preventDefault();

    const d = getDialog();
    frame = document.createElement("iframe");
    frame.src = `${ORIGIN}/player/v1/${link.dataset.skVideo}?autoplay=1&rel=0&description=0&music_info=0&closed_caption=0`;
    frame.title = link.getAttribute("aria-label") || "Video TikTok";
    frame.allow = "autoplay; fullscreen; encrypted-media; picture-in-picture";
    frame.allowFullscreen = true;
    d.querySelector(".sk-player__frame").replaceChildren(frame);
    d.querySelector(".sk-player__error").hidden = true;
    d.querySelector(".sk-player__open").href = link.href;
    setMuted(true);
    clearTimeout(readyTimer);
    readyTimer = setTimeout(showError, READY_TIMEOUT);
    document.documentElement.classList.add("sk-player-open");
    d.showModal();
    d.querySelector(".sk-player__close").focus();
  });
})();
