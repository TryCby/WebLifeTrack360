const AjaxClient = {
  // 1. Fetch API (GET/POST แบบ Promise-based)
  async fetchData(url, options = {}) {
    try {
      const defaultHeaders = {
        "Accept": "application/json",
        "X-Requested-With": "XMLHttpRequest"
      };

      // ถ้า body ไม่ใช่ FormData ให้ใส่ Content-Type เป็น JSON อัตโนมัติ
      if (options.body && !(options.body instanceof FormData)) {
        defaultHeaders["Content-Type"] = "application/json";
      }

      const config = {
        method: options.method || "GET",
        headers: {
          ...defaultHeaders,
          ...options.headers
        },
        ...options
      };

      const response = await fetch(url, config);

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      return await response.json();
    } catch (error) {
      console.error("Fetch API Error:", error);
      throw error;
    }
  },

  // 2. XMLHttpRequest (XHR แบบ Callback-based)
  sendXHR(method, url, data, callback) {
    const xhr = new XMLHttpRequest();
    xhr.open(method.toUpperCase(), url, true);
    xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");

    xhr.onreadystatechange = function () {
      if (xhr.readyState === XMLHttpRequest.DONE) {
        if (xhr.status >= 200 && xhr.status < 300) {
          try {
            const responseData = JSON.parse(xhr.responseText);
            callback(null, responseData);
          } catch (e) {
            callback(null, xhr.responseText);
          }
        } else {
          callback(new Error(`XHR Request failed with status ${xhr.status}`), null);
        }
      }
    };

    if (data && typeof data === "object" && !(data instanceof FormData)) {
      xhr.setRequestHeader("Content-Type", "application/json");
      xhr.send(JSON.stringify(data));
    } else {
      xhr.send(data);
    }
  }
};