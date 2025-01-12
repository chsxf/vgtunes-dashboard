function loadMostViewedPages() {
  let request = new XMLHttpRequest();
  request.open("GET", "{{ analytics.mostViewedPagesURL }}");
  request.setRequestHeader(
    "Data-Export-Access-Token",
    "{{ analytics.access_key }}"
  );
  request.addEventListener("load", onMostViewedPagesLoaded);
  request.send();
}

function onMostViewedPagesLoaded(e) {
  if (e.target.status == 200) {
    const parsedResponse = JSON.parse(e.target.responseText);
    resolveData(parsedResponse.data);
  }
}

function resolveData(data) {
  let sortedRows = [];
  let pathRE = /^\/albums\/(\w+)\/?$/;
  for (const key in data) {
    let slug = key;
    if ((reResult = pathRE.exec(slug))) {
      slug = reResult[1];
    }
    sortedRows.push([slug, data[key]]);
  }
  sortedRows.sort((_a, _b) => _b.hits - _a.hits);

  let request = new XMLHttpRequest();
  request.open("POST", "/Analytics/resolve");
  request.setRequestHeader("Content-Type", "application/json");
  request.addEventListener("load", onResolveLoaded);
  request.send(JSON.stringify(sortedRows));
}

function onResolveLoaded(e) {
  if (e.target.status == 200) {
    const parsedResponse = JSON.parse(e.target.responseText);
    fillTable(parsedResponse.resolved);
  }
}

function fillTable(data) {
  const tbody = document.getElementById("most-viewed-pages-tbody");
  if (data.length > 0) {
    tbody.children[0].remove();
    for (const row of data) {
      createRow(tbody, row);
    }
  } else {
    tbody.children[0].children[0].textContent = "No page found";
  }
}

function createRow(parentElement, rowData) {
  const tr = document.createElement("tr");
  tr.classList.add("align-middle");
  parentElement.appendChild(tr);

  const coverTD = document.createElement("td");
  if (rowData[2]) {
    const img = document.createElement("img");

    const src = document.createAttribute("src");
    src.value = `{{ analytics.cover_base_url|e('js') }}/${rowData[0]}/cover_100.webp`;
    img.setAttributeNode(src);

    const height = document.createAttribute("height");
    height.value = 50;
    img.setAttributeNode(height);

    coverTD.appendChild(img);
  } else {
    coverTD.appendChild(document.createTextNode(String.fromCharCode(160)));
  }
  tr.appendChild(coverTD);

  const titleTD = document.createElement("td");
  if (rowData[2]) {
    titleTD.appendChild(document.createTextNode(rowData[3]));
  } else {
    titleTD.appendChild(document.createTextNode(rowData[0]));
  }
  tr.appendChild(titleTD);

  const hitsTD = document.createElement("td");
  hitsTD.appendChild(document.createTextNode(rowData[1]));
  tr.appendChild(hitsTD);

  const linksTD = document.createElement("td");
  if (rowData[2]) {
    let days = new URLSearchParams(document.location.search).get("days");

    const link = document.createElement("a");
    const href = document.createAttribute("href");
    href.value = `/Album/show/${rowData[2]}${days ? `?days=${days}` : ""}`;
    link.setAttributeNode(href);
    link.classList.add("btn", "btn-outline-primary", "btn-sm");
    link.appendChild(document.createTextNode("View details"));
    linksTD.appendChild(link);
  } else {
    linksTD.appendChild(document.createTextNode(String.fromCharCode(160)));
  }
  tr.appendChild(linksTD);
}

(function () {
  loadMostViewedPages();
})();
