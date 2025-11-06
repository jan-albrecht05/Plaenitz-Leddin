const dropZone = document.getElementById("drop-zone");
dropZone.addEventListener("drop", dropHandler);

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
  // clear previous filename preview
  if (preview) preview.textContent = "";
  // Clear previous background (we'll set a new one if an image is found)
  clearDropzoneBackground();

  for (const file of files) {
    if (file.type && file.type.startsWith("image/")) {
      const li = document.createElement("li");
      const img = document.createElement("img");
      const objUrl = URL.createObjectURL(file);
      img.src = objUrl;
      img.alt = file.name;
      if (!currentBgUrl) {
        currentBgUrl = objUrl;
        setDropzoneBackground(objUrl);
      }
      if (preview) preview.appendChild(li);
    }
  }

  // If no images were found, ensure background is cleared
  if (!preview || preview.children.length === 0) {
    clearDropzoneBackground();
  }
}

function dropHandler(ev) {
  ev.preventDefault();
  const files = [...ev.dataTransfer.items]
    .map((item) => item.getAsFile())
    .filter((file) => file);
  displayImages(files);
}

const fileInput = document.getElementById("file-input");
fileInput.addEventListener("change", (e) => {
  displayImages(e.target.files);
});

// Clear preview
const clearBtn = document.getElementById("clear-btn");
clearBtn.addEventListener("click", () => {
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
if (typeof clearBtn !== 'undefined' && clearBtn) {
  clearBtn.addEventListener('click', () => {
    const rem = document.querySelector('input[name="remove_cover"]');
    if (rem) rem.value = '1';
  });
}