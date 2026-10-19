document.addEventListener("DOMContentLoaded", () => {
  // 1. จัดการปุ่ม Toggle เมนูบนมือถือ (ใช้การสลับคลาส nav-open เท่านั้น)
  const menuBtn = document.getElementById("menu-toggle-btn");
  const navMenu = document.getElementById("main-nav");

  if (menuBtn && navMenu) {
    menuBtn.addEventListener("click", () => {
      navMenu.classList.toggle("nav-open");
    });
  }

  // 2. ฟังก์ชันอัปเดตการ์ดแสดงผลตัวอย่างผ่าน DOM
  const updateStatCard = (cardId, value, statusText, badgeClass) => {
    const card = document.getElementById(cardId);
    if (!card) return;

    const valueEl = card.querySelector(".stat-value");
    const badgeEl = card.querySelector(".badge");

    if (valueEl) valueEl.textContent = value;
    if (badgeEl) {
      badgeEl.textContent = statusText;
      badgeEl.className = `badge ${badgeClass}`;
    }
  };

  console.log("LifeTrack 360: DOM fully loaded and initialized.");
});