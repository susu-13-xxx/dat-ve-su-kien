
const searchInput = document.getElementById("searchInput");
const categoryFilter = document.getElementById("categoryFilter");
const eventCards = document.querySelectorAll("#eventList .event-card");
const noResult = document.getElementById("noResult");

function filterEvents() {
    const keyword = searchInput.value.toLowerCase().trim();
    const category = categoryFilter.value;

    let count = 0;

    eventCards.forEach(card => {
        const name = card.querySelector("h3").textContent.toLowerCase();
        const type = card.dataset.category;

        const matchName = name.includes(keyword);
        const matchCategory = category === "all" || type === category;

        if (matchName && matchCategory) {
            card.style.display = "";
            count++;
        } else {
            card.style.display = "none";
        }
    });

    noResult.style.display = count === 0 ? "block" : "none";
}

if (searchInput && categoryFilter) {
    searchInput.addEventListener("input", filterEvents);
    categoryFilter.addEventListener("change", filterEvents);
}



// TÍNH TIỀN VÉ
const ticketType = document.getElementById("ticketType");
const ticketQuantity = document.getElementById("ticketQuantity");
const totalPrice = document.getElementById("totalPrice");

function updateTotal() {
    if (!ticketType || !ticketQuantity || !totalPrice) return;

    const price = Number(ticketType.value);
    const quantity = Math.max(1, Math.min(10, Number(ticketQuantity.value) || 1));

    ticketQuantity.value = quantity;

    totalPrice.textContent =
        (price * quantity).toLocaleString("vi-VN") + "đ";
}

if (ticketType && ticketQuantity) {
    ticketType.addEventListener("change", updateTotal);
    ticketQuantity.addEventListener("input", updateTotal);
}

// NÚT ĐẶT VÉ
function bookTicket() {
    if (!ticketType || !ticketQuantity) return;

    const quantity = Number(ticketQuantity.value);

    if (quantity < 1 || quantity > 10) {
        alert("Vui lòng chọn từ 1 đến 10 vé!");
        return;
    }

    alert("Bạn đã chọn vé thành công! Tiếp tục ở bước thanh toán nhé.");

    window.location.href = "checkout.php";
}




