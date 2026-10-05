const plans = document.querySelectorAll(".plus-select-plan");

plans.forEach((plan) => {
  plan.addEventListener("click", () => {

    // いったん全部の選択状態を解除
    plans.forEach((item) => {
      item.classList.remove("is-selected");
    });

    // クリックしたものだけ選択状態にする
    plan.classList.add("is-selected");
  });
});
