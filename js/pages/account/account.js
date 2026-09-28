const organizerTypes = document.querySelectorAll(
  'input[name="organizer_type"]'
);

const organizerFields = document.querySelectorAll(
  '.organizer-fields'
);

organizerTypes.forEach((radio) => {

  radio.addEventListener('change', () => {

    // 全ての入力欄を非表示・無効化
    organizerFields.forEach((field) => {

      field.hidden = true;

      field.querySelectorAll('input').forEach((input) => {
        input.disabled = true;
      });

    });

    // 選択された種類を取得
    const selectedType = document.querySelector(
      'input[name="organizer_type"]:checked'
    ).value;

    // 選択された入力欄
    const selectedFields = document.querySelector(
      `.organizer-fields[data-type="${selectedType}"]`
    );

    if (selectedFields) {

      selectedFields.hidden = false;

      selectedFields.querySelectorAll('input').forEach((input) => {
        input.disabled = false;
      });

    }

  });

});
