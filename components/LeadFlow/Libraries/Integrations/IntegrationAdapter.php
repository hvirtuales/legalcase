<?php

namespace Components\LeadFlow\Libraries\Integrations;

/**
 * La interfaz común de los sistemas jurídicos (§37).
 *
 * LeadFlow habla SOLO con esta interfaz; Docketwise es un adaptador. Incorporar
 * otro sistema es escribir otro adaptador, sin tocar el pipeline, la conversión
 * ni las pantallas.
 *
 * Los datos cruzan en una forma NEUTRAL (nombres propios de LeadFlow); cada
 * adaptador los traduce al formato de su proveedor. Todos los errores salen como
 * `IntegrationException`.
 */
interface IntegrationAdapter
{
    public function provider(): string;

    /** `simulated` | `live` */
    public function mode(): string;

    /** URL a la que se envía al usuario para autorizar la cuenta. */
    public function authorizeUrl(string $state): string;

    /** @return array{access_token:string,refresh_token:?string,expires_in:?int} */
    public function exchangeCode(string $code): array;

    /** @return array{access_token:string,refresh_token:?string,expires_in:?int} */
    public function refresh(string $refreshToken): array;

    /**
     * Una página de contactos.
     *
     * @return array{items:array<int,array{id:string,first_name:?string,last_name:?string,email:?string,phone:?string}>,next:?int}
     */
    public function listContacts(int $page): array;

    public function getContact(string $id): ?array;

    /**
     * @param array{first_name:string,middle_name:?string,last_name:string,email:?string,phone:?string,city:?string,country:?string} $data
     */
    public function createContact(array $data): string;

    /**
     * Completa datos de un contacto que ya existe, **sin pisar lo que no se manda**.
     *
     * Nació para reparar los contactos que se crearon sin teléfono, y por eso su
     * contrato es el de un parche: solo viajan las claves presentes en `$data`.
     *
     * @param array{first_name?:string,middle_name?:?string,last_name?:string,email?:?string,phone?:?string,city?:?string} $data
     */
    public function updateContact(string $id, array $data): void;

    /** @return array<string,string> id => nombre */
    public function listUsers(): array;

    /** @return array<string,string> id => nombre */
    public function listMatterTypes(): array;

    /** @param array{client_id:string,title:string,description:?string,user_ids:array} $data */
    public function createMatter(array $data): string;

    /** @param array{client_id:?string,matter_id:?string,title:string,content:string} $data */
    public function createNote(array $data): string;

    /** @param array{client_id:?string,matter_id:?string,title:string,filename:string,base64:string,description:?string} $data */
    public function createDocument(array $data): string;
}
