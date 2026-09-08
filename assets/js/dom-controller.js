document.addEventListener("DOMContentLoaded", () => {
  // 1. จัดการปุ่ม Toggle เมนูบนมือถือ
  const menuBtn = document.getElementById("menu-toggle-btn");
  const navMenu = document.getElementById("main-nav");

  if (menuBtn && navMenu) {
    menuBtn.addEventListener("click", () => {
      // สลับ class สำหรับ CSS
      navMenu.classList.toggle("nav-open");

      // บังคับสลับการแสดงผลโดยตรงเพื่อกันกรณี CSS ไม่โหลด
      if (navMenu.style.display === "block") {
        navMenu.style.display = "none";
      } else {
        navMenu.style.display = "block";
      }
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