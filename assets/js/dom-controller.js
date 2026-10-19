document.addEventListener("DOMContentLoaded", () => {
  // 1. จัดการปุ่ม Toggle เมนูบนมือถือ (ใช้การสลับคลาส nav-open)
  const menuBtn = document.getElementById("menu-toggle-btn");
  const navMenu = document.getElementById("main-nav");

  if (menuBtn && navMenu) {
    menuBtn.addEventListener("click", () => {
      navMenu.classList.toggle("nav-open");
    });
  }

  // 2. ป้องกันปัญหาแป้นพิมพ์มือถือหลุด Focus / ปิดตัวกะทันหัน
  const formInputs = document.querySelectorAll(
    "input:not([type='submit']):not([type='button']), textarea, select"
  );

  formInputs.forEach((el) => {
    // เมื่อแตะสัมผัส (touchend) ดึง Focus กลับมารองรับคีย์บอร์ดทันที
    el.addEventListener("touchend", () => {
      setTimeout(() => {
        if (document.activeElement !== el) {
          el.focus();
        }
      }, 50);
    });

    // ยืนยันการคลิกไม่ให้หลุด Focus
    el.addEventListener("click", () => {
      el.focus();
    });
  });

  // 3. ฟังก์ชันอัปเดตการ์ดแสดงผลตัวอย่างผ่าน DOM
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

  console.log("LifeTrack 360: DOM & Mobile Touch Listeners fully initialized.");
});