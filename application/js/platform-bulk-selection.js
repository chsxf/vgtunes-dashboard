const BULK_CHECKBOX_CLASS = ".bulk-edit-checkbox";
const BULK_SELECTION_FORM_ID = "#bulk_selection_form";
const BULK_SELECTION_FIELD_ID = "#bulk_selection";
const BULK_SELECTION_BUTTON_ID = "#bulk_selection_button";

function registerBulkCheckboxes() {
  $(BULK_CHECKBOX_CLASS).on("change", (e) => {
    e.preventDefault();
    updateSelectedCheckboxes();
  });
}

function updateSelectedCheckboxes() {
  $(BULK_SELECTION_BUTTON_ID).prop("disabled", getCheckedBoxes().length <= 1);
}

function registerBulkSelectButton() {
  $(BULK_SELECTION_BUTTON_ID).on("click", (e) => {
    e.preventDefault();

    const checkboxes = getCheckedBoxes();
    let bulkSelectionData = [];
    for (const checkbox of checkboxes) {
      const form = $(checkbox).parent().parent().find("form");
      if (form) {
        bulkSelectionData.push($(form).serialize());
      }
    }

    const serializedBulkSelectionData = JSON.stringify(bulkSelectionData);
    $(BULK_SELECTION_FIELD_ID).val(serializedBulkSelectionData);

    $(BULK_SELECTION_FORM_ID).submit();
  });
}

function getCheckedBoxes() {
  const checkboxes = $(BULK_CHECKBOX_CLASS);
  let checkedBoxes = [];
  for (const checkbox of checkboxes) {
    if (checkbox.checked) {
      checkedBoxes.push(checkbox);
    }
  }
  return checkedBoxes;
}

$(function () {
  registerBulkCheckboxes();
  registerBulkSelectButton();
});
