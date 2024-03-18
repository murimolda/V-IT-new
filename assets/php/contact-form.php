<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Проверка наличия данных
    if (!empty($_POST['name']) && !empty($_POST['email']) && !empty($_POST['message'])) {
        // Получение данных из формы
        $name = $_POST['name'];
        $email = $_POST['email'];
        $message = $_POST['message'];

        // Формирование заголовков электронного письма
        $to = "recipient@example.com"; // Укажите здесь свой адрес электронной почты
        $subject = "Сообщение с вашего сайта";
        $headers = "From: " . $name . " <" . $email . ">\r\n";
        $headers .= "Reply-To: " . $email . "\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        // Отправка письма
        if (mail($to, $subject, $message, $headers)) {
            echo "Ваше сообщение успешно отправлено.";
        } else {
            echo "При отправке сообщения произошла ошибка.";
        }
    } else {
        echo "Пожалуйста, заполните все поля формы.";
    }
} else {
    // Если запрос не является POST-запросом, перенаправьте пользователя на страницу с формой
    header("Location: contact_form.php");
    exit();
}
?>