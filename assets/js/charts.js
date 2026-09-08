const SimpleChart = {
  // 1. ฟังก์ชันวาดกราฟแท่ง (Bar Chart)
  drawBarChart(canvasId, labels, data, barColor = "#2563eb") {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const ctx = canvas.getContext("2d");

    const width = canvas.width;
    const height = canvas.height;
    const padding = 40;
    const chartHeight = height - padding * 2;
    const chartWidth = width - padding * 2;

    ctx.clearRect(0, 0, width, height);

    const maxValue = Math.max(...data, 10);
    const barWidth = chartWidth / data.length - 15;

    // วาดแกน X และ Y
    ctx.beginPath();
    ctx.strokeStyle = "#e5e7eb";
    ctx.moveTo(padding, padding);
    ctx.lineTo(padding, height - padding);
    ctx.lineTo(width - padding, height - padding);
    ctx.stroke();

    // วาดแท่งกราฟและข้อความ
    data.forEach((value, index) => {
      const barH = (value / maxValue) * chartHeight;
      const x = padding + index * (barWidth + 15) + 10;
      const y = height - padding - barH;

      ctx.fillStyle = barColor;
      ctx.fillRect(x, y, barWidth, barH);

      // ข้อความกำกับใต้แท่ง
      ctx.fillStyle = "#6b7280";
      ctx.font = "12px sans-serif";
      ctx.textAlign = "center";
      ctx.fillText(labels[index] || "", x + barWidth / 2, height - padding + 15);

      // ตัวเลขบนหัวแท่ง
      ctx.fillText(value.toString(), x + barWidth / 2, y - 5);
    });
  },

  // 2. ฟังก์ชันวาดกราฟเส้น (Line Chart)
  drawLineChart(canvasId, labels, data, lineColor = "#10b981") {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const ctx = canvas.getContext("2d");

    const width = canvas.width;
    const height = canvas.height;
    const padding = 40;
    const chartHeight = height - padding * 2;
    const chartWidth = width - padding * 2;

    ctx.clearRect(0, 0, width, height);

    const maxValue = Math.max(...data, 10);
    const stepX = chartWidth / (data.length - 1 || 1);

    // เส้นแกน
    ctx.beginPath();
    ctx.strokeStyle = "#e5e7eb";
    ctx.moveTo(padding, padding);
    ctx.lineTo(padding, height - padding);
    ctx.lineTo(width - padding, height - padding);
    ctx.stroke();

    // ลากเส้นกราฟ
    ctx.beginPath();
    ctx.strokeStyle = lineColor;
    ctx.lineWidth = 2;

    data.forEach((val, i) => {
      const x = padding + i * stepX;
      const y = height - padding - (val / maxValue) * chartHeight;
      if (i === 0) ctx.moveTo(x, y);
      else ctx.lineTo(x, y);
    });
    ctx.stroke();

    // วาดจุดวงกลมบนเส้น
    data.forEach((val, i) => {
      const x = padding + i * stepX;
      const y = height - padding - (val / maxValue) * chartHeight;

      ctx.beginPath();
      ctx.arc(x, y, 4, 0, Math.PI * 2);
      ctx.fillStyle = lineColor;
      ctx.fill();

      // ป้ายข้อความ
      ctx.fillStyle = "#6b7280";
      ctx.font = "12px sans-serif";
      ctx.textAlign = "center";
      ctx.fillText(labels[i] || "", x, height - padding + 15);
    });
  }
};