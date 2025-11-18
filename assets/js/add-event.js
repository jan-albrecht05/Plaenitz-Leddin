const dropZone = document.getElementById("drop-zone");
if (dropZone) {
  dropZone.addEventListener("drop", dropHandler);
}

window.addEventListener("drop", (e) => {
  if ([...e.dataTransfer.items].some((item) => item.kind === "file")) {
    e.preventDefault();
  }
});

// Dragover
dropZone.addEventListener("dragover", (e) => {
  const fileItems = [...e.dataTransfer.items].filter(
    (item) => item.kind === "file",
  );
  if (fileItems.length > 0) {
    e.preventDefault();
    if (fileItems.some((item) => item.type.startsWith("image/"))) {
      e.dataTransfer.dropEffect = "copy";
    } else {
      e.dataTransfer.dropEffect = "none";
    }
  }
});

window.addEventListener("dragover", (e) => {
  const fileItems = [...e.dataTransfer.items].filter(
    (item) => item.kind === "file",
  );
  if (fileItems.length > 0) {
    e.preventDefault();
    if (!dropZone.contains(e.target)) {
      e.dataTransfer.dropEffect = "none";
    }
  }
});

// Preview: show first image as background of the drop zone and keep a
// small DOM preview list for accessibility/filenames. We revoke previous
// object URLs to avoid leaking memory.
const preview = document.getElementById("preview");
let currentBgUrl = null;

function setDropzoneBackground(url) {
  if (!dropZone) return;
  dropZone.style.backgroundImage = `url(${url})`;
  dropZone.style.backgroundSize = "cover";
  dropZone.style.backgroundPosition = "center";
  dropZone.style.backgroundRepeat = "no-repeat";
  dropZone.classList.add("has-preview");
}

function clearDropzoneBackground() {
  if (!dropZone) return;
  dropZone.style.backgroundImage = "";
  dropZone.classList.remove("has-preview");
  if (currentBgUrl) {
    try {
      URL.revokeObjectURL(currentBgUrl);
    } catch (e) {
      // ignore
    }
    currentBgUrl = null;
  }
}

function displayImages(files) {
  if (!files || files.length === 0) {
    // No files: clear everything
    if (preview) preview.textContent = "";
    clearDropzoneBackground();
    return;
  }

  // Clear previous preview list (if it exists)
  if (preview) preview.textContent = "";
  // Clear previous background; we'll set a new one if an image is found
  clearDropzoneBackground();

  let firstImageSet = false;
  for (const file of files) {
    if (file && file.type && file.type.startsWith("image/")) {
      const li = document.createElement("li");
      const img = document.createElement("img");
      const objUrl = URL.createObjectURL(file);
      img.src = objUrl;
      img.alt = file.name;
      if (!firstImageSet) {
        firstImageSet = true;
        currentBgUrl = objUrl;
        setDropzoneBackground(objUrl);
      }
      if (preview) {
        li.appendChild(img);
        preview.appendChild(li);
      }
    }
  }
}

function dropHandler(ev) {
  ev.preventDefault();
  let files = [];
  if (ev.dataTransfer && ev.dataTransfer.items && ev.dataTransfer.items.length) {
    files = [...ev.dataTransfer.items]
      .map((item) => (item.kind === "file" ? item.getAsFile() : null))
      .filter((file) => file);
  } else if (ev.dataTransfer && ev.dataTransfer.files && ev.dataTransfer.files.length) {
    files = [...ev.dataTransfer.files];
  }
  displayImages(files);
  // Sync dropped files into the file input so they get uploaded
  if (fileInput && files.length) {
    const dt = new DataTransfer();
    for (const f of files) dt.items.add(f);
    fileInput.files = dt.files;
  }
}

const fileInput = document.getElementById("file-input");
if (fileInput) {
  fileInput.addEventListener("change", (e) => {
    displayImages(e.target.files);
  });
}

// Clear preview
const clearBtn = document.getElementById("clear-btn");
if (clearBtn) clearBtn.addEventListener("click", () => {
  // revoke all object URLs shown in the list (if present)
  if (preview) {
    for (const img of preview.querySelectorAll("img")) {
      try {
        URL.revokeObjectURL(img.src);
      } catch (e) {
        // ignore
      }
    }
    preview.textContent = "";
  }
  // clear dropzone background as well
  clearDropzoneBackground();
  const rem = document.querySelector('input[name="remove_cover"]');
  if (rem) rem.value = '1';
});

// On load, if server provided an existing cover image path in a hidden input,
// use it as the dropzone background so edit form shows current banner.
document.addEventListener('DOMContentLoaded', () => {
  const existing = document.querySelector('input[name="existing_cover_image"]');
  if (existing && existing.value) {
    // set directly (it's a normal URL/path, not an object URL)
    setDropzoneBackground(existing.value);
  }
  // ensure there's a hidden remove_cover input so clear can mark removal
  let rem = document.querySelector('input[name="remove_cover"]');
  if (!rem) {
    rem = document.createElement('input');
    rem.type = 'hidden';
    rem.name = 'remove_cover';
    rem.value = '0';
    const form = document.getElementById('event-form') || document.querySelector('form');
    if (form) form.appendChild(rem);
  }
});

// mark remove_cover when clear button clicked
// (additional clear handler unified above)