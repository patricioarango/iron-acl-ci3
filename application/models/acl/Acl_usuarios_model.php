<?php

/**
 * 13/11/2014
 * File: acl_usuarios_model.php
 * Encoding: ISO-8859-1
 * Project: ci_auth
 * Description of acl_usuarios_model
 *
 * @author Diego Olmedo
 */
class Acl_usuarios_model extends CI_Model
{

    const TABLA_USUARIO = "acl_usuario";
    const PK_TABLA_USUARIO = "id_acl_usuario";

    public function __construct()
    {
        parent::__construct();
    }

    public function get_all($iLimit = 0, $iOffset = 0, $sOrden = self::PK_TABLA_USUARIO, $sSentido = "desc", $bSoloActivos = FALSE)
    {
        $limit = (int) $iLimit;
        $where = "estado = 'VIGENTE'";
        $campos_orden = array("numero" => "id_acl_usuario", "nombre" => "nombre", "usuario" => "usuario", "activo" => "activo");
        $orden = isset($campos_orden[$sOrden]) ? $campos_orden[$sOrden] : self::PK_TABLA_USUARIO;

        if ($bSoloActivos === TRUE) {
            $where .= " AND activo = 'S'";
        }
        if ($limit > 0) {
            $offset = (int) $iOffset;
            $this->db->limit($limit, $offset);
        }
        $where .= $this->_get_filtro_busqueda();
        $this->db->where($where);
        $this->db->order_by($orden, $sSentido);
        return $this->db->get_where(self::TABLA_USUARIO, $where)->result_array();
    }

    public function get_activos($iLimit = 0, $iOffset = 0, $sOrden = self::PK_TABLA_USUARIO, $sSentido = "asc")
    {
        return $this->get_all($iLimit, $iOffset, $sOrden, $sSentido, TRUE);
    }

    public function count_all($bSoloActivos = FALSE)
    {
        $where = "estado = 'VIGENTE'";
        if ($bSoloActivos === TRUE) {
            $where .= " AND activo = 'S'";
        }
        $where .= $this->_get_filtro_busqueda();
        $this->db->where($where);
        return $this->db->count_all_results(self::TABLA_USUARIO);
    }

    public function count_activos()
    {
        return $this->count_all(TRUE);
    }

    public function buscar_por_id($iIdUsuario, $bSoloActivo = TRUE)
    {
        $id_usuario = (int) $iIdUsuario;
        $where = array(self::PK_TABLA_USUARIO => $id_usuario);
        if ($bSoloActivo === TRUE) {
            $where["activo"] = "S";
        }
        $query = $this->db->get_where(self::TABLA_USUARIO, $where);
        return $query->row_array();
    }

    public function actualizar($iIdUsuario, $aValues)
    {
        $id_usuario = (int) $iIdUsuario;
        $where = array(self::PK_TABLA_USUARIO => $id_usuario);
        $this->db->update(self::TABLA_USUARIO, $aValues, $where);
        return $this->db->affected_rows() >= 0;
    }

    public function insertar($aValues)
    {
        $this->db->insert(self::TABLA_USUARIO, $aValues);
        return $this->db->insert_id();
    }

    public function get_permisos_por_usuario($iIdUsuario)
    {
        $id_usuario = (int) $iIdUsuario;
        $permisos_por_usuario = $this->_get_permisos_seteados_por_usuario($id_usuario);
        return $this->_procesar_permisos_por_usuario($permisos_por_usuario);
    }

    public function get_grupos_por_usuario($iIdUsuario, $sOrden = "asignado", $sSentido = "desc")
    {
        $campos_orden = array("numero" => "id_acl_grupo", "nombre" => "nombre", "asignado" => "asignado");
        $orden = isset($campos_orden[$sOrden]) ? $campos_orden[$sOrden] : "asignado";
        //valido que no manden cualquier cosa en el sentido
        $sentido = strtolower($sSentido) === "desc" ? "desc" : "asc";

        $id_usuario = (int) $iIdUsuario;
        $permisos_por_usuario = $this->_get_grupos_seteados_por_usuario($id_usuario, $orden, $sentido);
        return $permisos_por_usuario;
//        return $this->_procesar_grupos_por_usuario($permisos_por_usuario);
    }

    public function cambiar_activo($sValue, $iIdUsuario)
    {
        $id_usuario = (int) $iIdUsuario;
        $value = $sValue;
        $this->db->update(self::TABLA_USUARIO, array("activo" => $value), array(self::PK_TABLA_USUARIO => $id_usuario));
        return $this->db->affected_rows() >= 0;
    }

    public function cambiar_contrasenia($sValue, $iIdUsuario)
    {
        $id_usuario = (int) $iIdUsuario;
        $value = $sValue;
        $this->db->update(self::TABLA_USUARIO, array("contrasenia" => $value), array(self::PK_TABLA_USUARIO => $id_usuario));
        return $this->db->affected_rows() >= 0;
    }

    public function eliminar_usuarios($aIdsUsuarios)
    {
        $ids_usuario = (array) $aIdsUsuarios;
        $values = array("activo" => "N", "estado" => "ELIMINADO");
        foreach ($ids_usuario as $id_usuario) {
            $where[self::PK_TABLA_USUARIO] = $id_usuario;
            $this->db->update(self::TABLA_USUARIO, $values, $where);
        }
        return TRUE;
    }

//PRIVATE
    private function _get_filtro_busqueda()
    {
        $like = "";
        if ($this->input->get("texto_buscar")) {
            $texto = $this->db->escape_like_str($this->input->get("texto_buscar"));
            $like = " AND (nombre LIKE '%$texto%' OR apellido LIKE '%$texto%' OR usuario LIKE '%$texto%')";
        }
        return $like;
    }

    private function _get_permisos_seteados_por_usuario($iIdUsuario)
    {
        $id_usuario = (int) $iIdUsuario;
        $this->db->where("fk_acl_usuario", $id_usuario);
        $rows = $this->db->get('acl_usuario_permiso')->result_array();
        $permisos = array();
        foreach ($rows as $row) {
            $permisos[$row['fk_acl_permiso']] = $row;
        }
        return $permisos;
    }

    private function _procesar_permisos_por_usuario($aPermisosSeteadosPorUsuario)
    {
        $permisos_requeridos = $this->_get_permisos_seteados(TRUE);
        $permisos_por_usuario = (array) $aPermisosSeteadosPorUsuario;
        $mis_permisos = array();
        $index = 0;
        foreach ($permisos_requeridos as &$permiso) {
            $permiso["permitido"] = NULL;
            $permiso["denegado"] = NULL;
            if (isset($permisos_por_usuario[$permiso["id_acl_permiso"]])) {
                $tipo_permiso = (int) $permisos_por_usuario[$permiso["id_acl_permiso"]]["tipo_permiso"];
                $permiso["permitido"] = $tipo_permiso === 1 ? 1 : 0;
                $permiso["denegado"] = $tipo_permiso === 0 ? 1 : 0;
            }
            $nombre_clase = mb_strtolower($permiso["controlador"]);
            if ( ! isset($mis_permisos[$nombre_clase])) {
                $mis_permisos[$nombre_clase] = array();
            }
            array_push($mis_permisos[$nombre_clase], $permiso);
            $index ++;
        }
        return $mis_permisos;
    }

    private function _get_grupos_seteados_por_usuario($iIdUsuario, $sOrderBy = "asignado", $sSentido = "desc")
    {
        $id_usuario = (int) $iIdUsuario;
        $sql = "SELECT g.*, IF(id_acl_usuario_grupo IS NOT NULL, 1,0) AS asignado
                    FROM
                `acl_grupo` g
                    LEFT JOIN `acl_usuario_grupo` ug
                    ON g.`id_acl_grupo` = ug.`fk_acl_grupo` AND ug.`fk_acl_usuario` = {$id_usuario}
                ORDER BY {$sOrderBy} {$sSentido}";

        $rows = $this->db->query($sql)->result_array();
        $grupos = array();
        foreach ($rows as $row) {
            $grupos[$row['id_acl_grupo']] = $row;
        }
        return $grupos;
    }

    private function _get_permisos_seteados()
    {
        $this->load->model("acl/acl_permisos_model", "model_permisos");
        return $this->model_permisos->get_permisos_seteados();
    }

}