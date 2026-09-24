"use strict";
$(document).ready(function () {
    select2();
    datatable();
    ckediter();
    setInterval(() => {
        feather.replace();
    }, 1000);
});

$(document).on("click", ".customModal", function () {
    var modalTitle = $(this).data("title");
    var modalUrl = $(this).data("url");
    var modalSize = $(this).data("size") == "" ? "md" : $(this).data("size");
    $("#customModal .modal-title").html(modalTitle);
    $("#customModal .modal-dialog").addClass("modal-" + modalSize);
    $.ajax({
        url: modalUrl,
        success: function (result) {
            if (result.status == "error") {
                notifier.show(
                    "Hata",
                    result.messages,
                    "error",
                    errorImg,
                    4000,
                );
            } else {
                $("#customModal .body").html(result);
                $("#customModal").modal("show");
                select2();
                ckediter();
            }
        },
        error: function (result) {},
    });
});

// basic message
$(document).on("click", ".confirm_dialog", function (e) {
    var title = $(this).attr("data-dialog-title");
    if (title == undefined) {
        var title = "Bu kaydı silmek istediğinizden emin misiniz?";
    }
    var text = $(this).attr("data-dialog-text");
    if (text == undefined) {
        var text =
            "Silinen kayıt geri alınamaz. Devam etmek istiyor musunuz?";
    }
    var dialogForm = $(this).closest("form");
    Swal.fire({
        title: title,
        text: text,
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Evet",
        cancelButtonText: "Vazgeç",
    }).then((data) => {
        if (data.isConfirmed) {
            dialogForm.submit();
        }
    });
});

// common
$(document).on("click", ".common_confirm_dialog", function (e) {
    var dialogForm = $(this).closest("form");
    var actions = $(this).data("actions");
    Swal.fire({
        title: "Bu kaydı silmek istediğinizden emin misiniz?",
        text: "Silinen kayıt geri alınamaz. Devam etmek istiyor musunuz?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Evet",
        cancelButtonText: "Vazgeç",
    }).then((data) => {
        if (data.isConfirmed) {
            dialogForm.submit();
        }
    });
});

$(document).on("click", ".fc-day-grid-event", function (e) {
    e.preventDefault();
    var event = $(this);
    var modalTitle = $(this).find(".fc-content .fc-title").html();
    var modalSize = "md";
    var modalUrl = $(this).attr("href");
    $("#customModal .modal-title").html(modalTitle);
    $("#customModal .modal-dialog").addClass("modal-" + modalSize);
    $.ajax({
        url: modalUrl,
        success: function (result) {
            $("#customModal .modal-body").html(result);
            $("#customModal").modal("show");
        },
        error: function (result) {},
    });
});

function toastrs(title, message, status) {
    if (status == "success") {
        notifier.show("İşlem tamamlandı", message, "success", successImg, 4000);
    } else {
        notifier.show("Hata", message, "error", errorImg, 4000);
    }
}

function convertArrayToJson(form) {
    var data = $(form).serializeArray();
    var indexed_array = {};

    $.map(data, function (n, i) {
        indexed_array[n["name"]] = n["value"];
    });

    return indexed_array;
}

function select2() {
    $.fn.select2.defaults.set('language', {
        errorLoading: function () { return 'Sonuçlar yüklenemedi.'; },
        inputTooLong: function (args) { return 'En fazla ' + args.maximum + ' karakter girebilirsiniz.'; },
        inputTooShort: function (args) { return 'En az ' + args.minimum + ' karakter girin.'; },
        loadingMore: function () { return 'Daha fazla sonuç yükleniyor...'; },
        maximumSelected: function (args) { return 'En fazla ' + args.maximum + ' seçim yapabilirsiniz.'; },
        noResults: function () { return 'Sonuç bulunamadı.'; }, searching: function () { return 'Aranıyor...'; },
        removeAllItems: function () { return 'Tüm seçimleri kaldır'; }, removeItem: function () { return 'Seçimi kaldır'; }
    });
    if ($(".select2").length > 0) {
        $(".select2").each(function () {
            let $modalParent = $(this).closest("form");

            let dropdownParent = $modalParent.length
                ? $modalParent
                : $(document.body);

            $(this).select2({
                width: "100%",
                dropdownParent: dropdownParent,
            });
        });
    }
}

function ckediter(editer_id = "") {
    if (editer_id == "") {
        editer_id = "#classic-editor";
    }
    if ($(editer_id).length > 0) {
        ClassicEditor.create(document.querySelector(editer_id), {language: 'tr'})
            .then((editor) => {})
            .catch((error) => {
                console.error(error);
            });
    }
}

function datatable() {
    $.extend(true, $.fn.dataTable.defaults, {
        language: {
            emptyTable: "Gösterilecek kayıt yok", info: "Toplam _TOTAL_ kayıttan _START_–_END_ arası gösteriliyor",
            infoEmpty: "Gösterilecek kayıt yok", infoFiltered: "(_MAX_ kayıt içinden süzüldü)",
            lengthMenu: "Sayfada _MENU_ kayıt göster", loadingRecords: "Yükleniyor...", processing: "İşleniyor...",
            search: "Ara:", zeroRecords: "Eşleşen kayıt bulunamadı", decimal: ",", thousands: ".",
            paginate: {first: "İlk", last: "Son", next: "Sonraki", previous: "Önceki"},
            aria: {orderable: "Sıralamayı değiştir", orderableReverse: "Ters sırala", orderableRemove: "Sıralamayı kaldır"},
            buttons: {copy: "Kopyala", print: "Yazdır", colvis: "Sütunlar", copyTitle: "Panoya kopyalandı",
                copySuccess: {_: "%d satır kopyalandı", 1: "1 satır kopyalandı"},
                copyKeys: "Kopyalamak için Ctrl+C veya ⌘+C tuşlarına basın. Çıkmak için Esc tuşuna basın."}
        }
    });
    if ($(".basic-datatable").length > 0) {
        $(".basic-datatable").DataTable({
            scrollX: true,
            ordering: false,
            dom: "Bfrtip",
            buttons: ["copy", "csv", "excel", "print"],
        });
    }

    if ($(".advance-datatable").length > 0) {
        $(".advance-datatable").DataTable({
            scrollX: true,
            ordering: false,
            stateSave: false,
            dom: "Bfrtip",
            buttons: [
                {
                    extend: "excelHtml5",
                    exportOptions: {
                        columns: ":visible",
                    },
                },
                {
                    extend: "pdfHtml5",
                    exportOptions: {
                        columns: ":visible",
                    },
                },
                {
                    extend: "copyHtml5",
                    exportOptions: {
                        columns: ":visible",
                    },
                },

                "colvis",
            ],
        });
    }
}
$(document).on("click", ".aiModal", function (e) {
    e.preventDefault();

    const $el = $(this);
    const title = $el.data("title") || "Ayrıntılar";
    const size = $el.data("size") || "md";
    const url = $el.data("url");
    const validate = $el.data("validate");
    const id = validate ? $(validate).val() : "";

    $("#aiModal .modal-title").html(title);
    $.ajax({
        url: url,
        type: "GET",
        data: {
            id: id,
        },
        success: function (response) {
            $("#aiModal .modal-body").html(response);

            if (typeof taskCheckbox === "function") taskCheckbox();
            if (typeof select2 === "function") select2();

            $("#aiModal").modal("show");
        },
        error: function (xhr) {
            let msg = "İşlem tamamlanamadı. Lütfen tekrar deneyin.";

            showAiMessage("error", msg, "error");
        },
    });
});

function taskCheckbox() {
    const $checkboxes = $("#check-list input[type=checkbox]");

    const total = $checkboxes.length;
    const checked = $checkboxes.filter(":checked").length;

    const percentage = total ? Math.round((checked / total) * 100) : 0;

    const $progress = $("#taskProgress");

    $(".custom-label").text(percentage + "%");
    $progress.css("width", percentage + "%");
    $progress.removeClass("bg-danger bg-warning bg-primary bg-success");
    let progressClass = "bg-success";

    if (percentage <= 15) {
        progressClass = "bg-danger";
    } else if (percentage <= 33) {
        progressClass = "bg-warning";
    } else if (percentage <= 70) {
        progressClass = "bg-primary";
    }

    $progress.addClass(progressClass);
}
function showAiMessage(title, message, status) {
    const $msg = $("#aiMessage");
    $msg.html(
        `<div class="alert alert-${status === "success" ? "success" : "danger"} m-0"> ${message}</div>`,
    );
    setTimeout(() => $msg.html(""), 4000); // hide after 4s
}
