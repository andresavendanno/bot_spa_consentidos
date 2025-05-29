<?php
    const TOKEN_ANDERCODE = "ANDERCODEPHPAPIMETA"; // TOKEN_AVENCODE = "AVENCODEPHPAPIMETA"
    const WEBHOOK_URL = "https://anderson-bastidas.com/webhook.php"; // COMENTARIO ACÁ VA EL URL DEL ARCHIVO WEBHOOK QUE ME DARÁ LA APP LUGO DE TENER MI DOMINIO
// verificar minuscular/mayusculas siempre
    function VerificarToken($req,$res){
        try{
            $token = $req['hub_verify_token'];
            $challenge = $req['hub_challenge'];

            if(isset($challenge) && isset($token) && isset($token) == TOKEN_ANDERCODE){
                $res -> send($challenge);
            }else{
                $res ->status(400)->send()
            }

        }catch(Exception $e){
            $res ->status(400)->send();
        }
    }

    function RecibirMensajes($req,$res){
       try{  
            $entry = $req['entry'][0];
            $changes = $entry['changes'][0];
            $value = $changes['value'];
            $objetomensaje = $value['message'];
            $mensaje = $objetomensaje[0];

            $comentario = $mensaje['text']['body'];
            $numero = $mensaje['from'];
            
            EnviarMensajeWhatsapp($comentario,$numero);

            $archivo = fopen("log.txt","a"); // hay que tener creardo el archivo log.txt en el hostinger apra poder hacerlo
            $texto = json_encode($numero);
            fwrite($archivo,$texto);
            fclose($archivo);

             $res ->send("EVENT_RECEIVED");
        }catch(Exception $e){
            $res ->send("EVENT_RECEIVED");
        }
    }

    function EnviarMensajeWhatsapp($comentario,$numero){ // acá es donde debo enfocar las respuestas
        $comentario = strtolower($comentario);
        
        if(strpos($comentario,'hola')!==false){  // CUANDO ESCRIBA "HOLA" RESPONDE LO DETERMINADO ABAJO (minisculas incluso supongo)
            $data = json_encode([
                  "messaging_product"=> "whatsapp",
                  "recipient_type"=> "individual", 
                  "to"=> "573506117767",
                  "type"=> "text",
                  "text"=> [
                        "preview_url"=> false,
                        "body"=> "si todo salio bien, esto responde al bot al escribirle"
                    ]
                  ]);
        } else if($comentario=='1'){ //acá el 1 se relaciona con lo de abajo y pude ser cualqui
        $data = json_encode([
                  "messaging_product"=> "whatsapp",
                  "recipient_type"=> "individual", 
                  "to"=> "573506117767",
                  "type"=> "text",
                  "text"=> [
                        "preview_url"=> false,
                        "body"=> "si todo salio bien, esto responde al bot al escribirle"
                    ]
                  ]);
        }else{
            $data = json_encode([
                  "messaging_product"=> "whatsapp",
                  "recipient_type"=> "individual", 
                  "to"=> "573506117767",
                  "type"=> "text",
                  "text"=> [
                        "preview_url"=> false, //abajo insertar emojis -> trato diferente a usuarios nuevos y antiguos 
                        "body"=> "Hola, buen día -emoji-\nGracias por comunicarte con Spa Consentidos (cursiva-negrilla-etc)\n\nPara solicitar un turno te pedimos que respondas:\n\n*Dia y horario a solicitar?\n*que servicio queres?\nLos precios estan en el catalogo del perfil\n\nGracias)emojimanos)"///VAMOS ACA REVISANDO EL PRIMER MENSAJE
                    ]

                  ]);
        }
        $options = [
            'http' => [ //actualizar el toquen y revisar header puedo tenerlo mal
                'method' => 'POST',
                'header' => "Content-type: application/json\r\nAuthorization: Bearer EAAUZAHdaMZB7sBOxZAC7zBzoZAfD9hzjyppGuY6ZCC7kvlKtcUSxfeJNOJyZChYZCZAigTtVkldjRRGOPaHiyn5BitHCsZAeRXLzWEpT0cYwMpaGYyDhOqlcp0YU4WbtoR1fm339t1Ow22GSY52C1CZCapFYASb7wDUgHdZBqsWL3vCXjbGCLoWIWiwPYdv2WLH9IsRc1eFn0sbadGpsK1M4HhjDefGkctP4hjzZA0ONKHJzdqQf\r\n",
                'content' => $data,
                'ignore_errors' => true
            ]
        ];

        $context = stream_context_create($options);
        $response = file_get_contents('https://graph.facebook.com/v22.0/646389751893147/messages',false,$context); //revisar el link que puede variar constantemente o cada x tiempro

        if($response === false){
            echo "error al enviar el mensaje\n";
        }else{
            echo "mensaje enviado correctamente\n";
        }
    }

    if($_SERVER['REQUEST_METHOD']==='POST'){
        $input = file_get_contents('php://input');
        $data = json_decode($input,true);

        recibirMensajes($data,http_response_code());
    }else if($_SERVER['REQUEST_METHOD']=== 'GET'){
        if(isset($_GET['hub_mode']) && isset($_GET['hub_verify_token']) && isset($_GET['hub_challenge']) && $_GET['hub_mode'] === 'suscribe' &&$_GET['hub_verify_token']=== TOKEN_ANDERCODE){
            echo $_GET['hub_challenge'];
        }else{
            http_response_code(403)
        }
    }
?>