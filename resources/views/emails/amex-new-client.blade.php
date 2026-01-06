<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nuevo cliente Amex</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #006FCF;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #006FCF;
            margin: 0;
            font-size: 24px;
        }
        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #006FCF;
            margin-top: 25px;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e9ecef;
        }
        .content {
            margin-bottom: 30px;
        }
        .field {
            margin-bottom: 15px;
            padding: 12px;
            background-color: #f8f9fa;
            border-radius: 5px;
            border-left: 4px solid #006FCF;
        }
        .field-label {
            font-weight: bold;
            color: #006FCF;
            margin-bottom: 5px;
            display: block;
            font-size: 14px;
        }
        .field-value {
            color: #333;
            word-wrap: break-word;
            font-size: 15px;
        }
        .field-value.empty {
            color: #6c757d;
            font-style: italic;
        }
        .info-adicional {
            background-color: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-top: 10px;
            white-space: pre-wrap;
        }
        .footer {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            color: #6c757d;
            font-size: 14px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Nuevo Cliente Amex</h1>
        <p>Readymarket</p>
    </div>

    <div class="content">
        <!-- Información de Contacto -->
        <div class="section-title">📋 Información de Contacto</div>

        <div class="field">
            <span class="field-label">Nombre:</span>
            <span class="field-value {{ !$form->contacto_nombre ? 'empty' : '' }}">
                {{ $form->contacto_nombre ?? 'No proporcionado' }}
            </span>
        </div>

        <div class="field">
            <span class="field-label">Apellidos:</span>
            <span class="field-value {{ !$form->contacto_apellidos ? 'empty' : '' }}">
                {{ $form->contacto_apellidos ?? 'No proporcionado' }}
            </span>
        </div>

        <div class="field">
            <span class="field-label">Correo electrónico:</span>
            <span class="field-value {{ !$form->contacto_email ? 'empty' : '' }}">
                {{ $form->contacto_email ?? 'No proporcionado' }}
            </span>
        </div>

        <div class="field">
            <span class="field-label">Teléfono:</span>
            <span class="field-value {{ !$form->contacto_telefono ? 'empty' : '' }}">
                {{ $form->contacto_telefono ?? 'No proporcionado' }}
            </span>
        </div>

        <!-- Información de la Empresa -->
        <div class="section-title">🏢 Información de la Empresa</div>

        <div class="field">
            <span class="field-label">Nombre de la empresa:</span>
            <span class="field-value {{ !$form->empresa_nombre ? 'empty' : '' }}">
                {{ $form->empresa_nombre ?? 'No proporcionado' }}
            </span>
        </div>

        <div class="field">
            <span class="field-label">RFC:</span>
            <span class="field-value {{ !$form->empresa_rfc ? 'empty' : '' }}">
                {{ $form->empresa_rfc ?? 'No proporcionado' }}
            </span>
        </div>

        <div class="field">
            <span class="field-label">Ciudad:</span>
            <span class="field-value {{ !$form->empresa_ciudad ? 'empty' : '' }}">
                {{ $form->empresa_ciudad ?? 'No proporcionado' }}
            </span>
        </div>

        <div class="field">
            <span class="field-label">Estado:</span>
            <span class="field-value {{ !$form->empresa_estado ? 'empty' : '' }}">
                {{ $form->empresa_estado ?? 'No proporcionado' }}
            </span>
        </div>

        <div class="field">
            <span class="field-label">Código Postal:</span>
            <span class="field-value {{ !$form->empresa_codigo_postal ? 'empty' : '' }}">
                {{ $form->empresa_codigo_postal ?? 'No proporcionado' }}
            </span>
        </div>

        <div class="field">
            <span class="field-label">Ingresos Anuales:</span>
            <span class="field-value {{ !$form->empresa_ingresos_anuales ? 'empty' : '' }}">
                {{$form->empresa_ingresos_anuales ?? 'No proporcionado' }}
            </span>
        </div>

        <div class="field">
            <span class="field-label">Información Adicional:</span>
            @if($form->empresa_info_adicional)
                <div class="info-adicional">{{ $form->empresa_info_adicional }}</div>
            @else
                <span class="field-value empty">No proporcionado</span>
            @endif
        </div>
    </div>

    <div class="footer">
        <p>Este mensaje fue enviado desde el formulario de nuevos clientes Amex de Readymarket.</p>
        <p>Fecha de envío: {{ $form->created_at ? $form->created_at->format('d/m/Y H:i:s') : date('d/m/Y H:i:s') }}</p>
    </div>
</div>
</body>
</html>
