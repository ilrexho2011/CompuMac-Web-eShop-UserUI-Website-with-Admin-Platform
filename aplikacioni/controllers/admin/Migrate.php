<?php
class Migrate extends CI_Controller
{
    public function index()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            $this->load->library('migration');
            if ($this->migration->latest() === FALSE) {
                show_error($this->migration->error_string());
            } else {
                echo "Migration Successfully";
            }
        } else {
            echo "You are not authorized to do this";
        }
    }
    public function rollback($version = '')
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin() && defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 1) {
            $this->load->library('migration');
            if (!empty($version) && is_numeric($version)) {
                $this->migration->version($version);
            } else {
                show_error($this->migration->error_string());
            }
        } else {
            echo "You are not authorized to do this";
        }
    }
    public function change_migration_version()
    {
        $data = ['version' => 26];

        $this->db->where('1=1'); // Ensures the single row is updated
        $this->db->update('migrations', $data);

        die();

        // if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
        //     $this->load->library('migration');
        //     $migration_version = 100; // Replace with your specific migration version

        //     // Get current migration version
        //     $current_version = $this->db->get('migrations')->row()->version;

        //     if ($current_version == $migration_version) {
        //         echo "Migration version {$migration_version} is already applied.";
        //     } else {
        //         if ($this->migration->version($migration_version) === FALSE) {
        //             show_error($this->migration->error_string());
        //         } else {
        //             echo "Migration to version {$migration_version} executed successfully.";
        //         }
        //     }
        // }
    }
}
