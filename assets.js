(function () {
  if (!window.APP_CONFIG) return; // Exit if config is missing

  const IS_ADMIN = window.APP_CONFIG.isAdmin;
  const MY_ID = window.APP_CONFIG.myId;
  const MY_NAME = window.APP_CONFIG.myName;
  const MY_AVATAR = window.APP_CONFIG.myAvatar;
  const API = "?chat_api=";

  let isOpen = false;
  let currentRoom = null;
  let lastMsgId = 0;
  let pollTimer = null;

  window.chatToggle = function () {
    isOpen = !isOpen;
    document.getElementById("chat-win").classList.toggle("open", isOpen);
    if (isOpen) {
      if (!IS_ADMIN) {
        startPoll();
        scrollBottom();
      } else {
        loadRooms();
      }
    } else {
      stopPoll();
    }
  };

  window.chatClose = function () {
    isOpen = false;
    document.getElementById("chat-win").classList.remove("open");
    stopPoll();
  };

  window.chatBack = function () {
    if (!IS_ADMIN) return;
    currentRoom = null;
    lastMsgId = 0;
    stopPoll();
    document.getElementById("chat-msgs-panel").style.display = "none";
    document.getElementById("chat-rooms-panel").style.display = "flex";
    document.getElementById("chat-back").style.display = "none";
    document.getElementById("chat-header-title").innerHTML =
      'Chat Pengguna <span class="chat-24">24 jam</span>';
    document.getElementById("chat-header-sub").textContent =
      "Pilih pengguna untuk membalas";
    document.getElementById("chat-msgs").innerHTML = "";
    loadRooms();
  };

  function loadRooms() {
    fetch(API + "rooms")
      .then((r) => r.json())
      .then((data) => {
        const el = document.getElementById("chat-rooms-list");
        if (!data.rooms || data.rooms.length === 0) {
          el.innerHTML =
            '<div class="chat-rooms-empty">💬 Belum ada percakapan masuk</div>';
          return;
        }
        el.innerHTML = data.rooms
          .map(
            (r) => `
        <div class="chat-room-item" data-uid="${r.id}" data-name="${encodeURIComponent(r.name)}" data-avatar="${encodeURIComponent(r.avatar || "👤")}">
          <div class="cr-av">${h(r.avatar || "?")}</div>
          <div class="cr-info">
            <div class="cr-name">${h(r.name)}</div>
            <div class="cr-last">${h(r.last_msg || "")}</div>
          </div>
          ${+r.unread > 0 ? `<div class="cr-badge">${+r.unread}</div>` : ""}
        </div>
      `,
          )
          .join("");
        el.querySelectorAll(".chat-room-item").forEach((item) => {
          item.addEventListener("click", function () {
            openRoom(
              parseInt(this.dataset.uid),
              decodeURIComponent(this.dataset.name),
              decodeURIComponent(this.dataset.avatar),
            );
          });
        });
      });
  }

  function openRoom(userId, userName, userAvatar) {
    currentRoom = userId;
    lastMsgId = 0;
    document.getElementById("chat-rooms-panel").style.display = "none";
    const mp = document.getElementById("chat-msgs-panel");
    mp.style.display = "flex";
    document.getElementById("chat-back").style.display = "";
    document.getElementById("chat-header-title").innerHTML =
      h(userName) + ' <span class="chat-24">24 jam</span>';
    document.getElementById("chat-header-sub").textContent = "Pengguna";
    document.getElementById("chat-msgs").innerHTML =
      '<div style="text-align:center;color:var(--txt3);font-size:12px;padding:20px 0;">⏳ Memuat pesan...</div>';
    startPoll();
  }

  window.chatSend = function () {
    if (IS_ADMIN && !currentRoom) return;
    const ta = document.getElementById("chat-textarea");
    const msg = ta.value.trim();
    if (!msg) return;
    ta.value = "";
    ta.style.height = "auto";

    const body = new FormData();
    body.append("msg", msg);
    if (IS_ADMIN) body.append("room_user_id", currentRoom);

    const chatMode = window.APP_CONFIG.chatMode;
    const typingEl = document.getElementById("chat-typing");
    if (!IS_ADMIN && (chatMode === "ai_auto" || chatMode === "hybrid")) {
      typingEl.style.display = "block";
    }

    fetch(API + "send", { method: "POST", body })
      .then((r) => r.json())
      .then((d) => {
        if (d.ok) {
          setTimeout(
            () => {
              typingEl.style.display = "none";
              fetchMsgs();
            },
            !IS_ADMIN && (chatMode === "ai_auto" || chatMode === "hybrid")
              ? 1200
              : 0,
          );
        }
      });
  };

  function startPoll() {
    stopPoll();
    fetchMsgs();
    pollTimer = setInterval(fetchMsgs, 3000);
  }
  function stopPoll() {
    if (pollTimer) {
      clearInterval(pollTimer);
      pollTimer = null;
    }
  }

  function fetchMsgs() {
    let url = API + "fetch&since=" + lastMsgId;
    if (IS_ADMIN && currentRoom) url += "&room_user_id=" + currentRoom;
    else if (IS_ADMIN) return;

    fetch(url)
      .then((r) => r.json())
      .then((data) => {
        if (IS_ADMIN) {
          let tot = Object.values(data.unread || {}).reduce((a, b) => a + b, 0);
          setBadge(tot);
        } else {
          setBadge(data.unread || 0);
        }

        if (!data.msgs || data.msgs.length === 0) return;
        const container = document.getElementById("chat-msgs");
        const ph = container.querySelector('[style*="padding:20px"]');
        if (ph) ph.remove();

        data.msgs.forEach((m) => {
          if (m.id <= lastMsgId) return;
          lastMsgId = Math.max(lastMsgId, +m.id);
          const isMine = +m.sender_id === MY_ID;
          const timeStr = new Date(m.created_at).toLocaleTimeString("id-ID", {
            hour: "2-digit",
            minute: "2-digit",
          });
          const div = document.createElement("div");
          div.className = "msg-row" + (isMine ? " mine" : "");
          div.innerHTML = `<div class="msg-av">${h(m.sender_avatar)}</div><div class="msg-wrap"><div class="msg-bubble">${escHtml(m.message)}</div><div class="msg-time">${timeStr}</div></div>`;
          container.appendChild(div);
        });
        scrollBottom();
      });
  }

  function setBadge(n) {
    const fab = document.getElementById("fab-badge");
    const sid = document.getElementById("sidebar-badge");
    if (n > 0) {
      const label = n > 99 ? "99+" : n;
      fab.textContent = label;
      fab.style.display = "flex";
      if (sid) {
        sid.textContent = label;
        sid.style.display = "flex";
      }
    } else {
      fab.style.display = "none";
      if (sid) sid.style.display = "none";
    }
  }

  function scrollBottom() {
    const el = document.getElementById("chat-msgs");
    el.scrollTop = el.scrollHeight;
  }
  function h(s) {
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }
  function escHtml(s) {
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\n/g, "<br>");
  }

  // Auto-poll unread badge
  setInterval(() => {
    if (!IS_ADMIN) {
      fetch(API + "fetch&since=9999999")
        .then((r) => r.json())
        .then((d) => setBadge(d.unread || 0));
    } else {
      fetch(API + "rooms")
        .then((r) => r.json())
        .then((data) => {
          if (!data.rooms) return;
          setBadge(data.rooms.reduce((a, r) => a + +r.unread, 0));
        });
    }
  }, 8000);
})();
