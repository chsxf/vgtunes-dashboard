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
  const button = $(BULK_SELECTION_BUTTON_ID);
  const checkedBoxCount = getCheckedBoxes().length;
  const isDisabled = checkedBoxCount <= 1;
  button.prop("disabled", isDisabled);
  const baseText = "Select Multiple";
  button.text(isDisabled ? baseText : `${baseText} (${checkedBoxCount})`);
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
