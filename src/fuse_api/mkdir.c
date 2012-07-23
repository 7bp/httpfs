#include "../phpfs.h"

int phpfs_mkdir( const char *path ,
                 mode_t mode )
{
    struct raw_data raw_data = { 0 };
    struct
    {
        uint32_t mode;
    }
    header = { htonl( mode | S_IFDIR ) };

    PHPFS_DO_REQUEST( PHPFS_OPCODE_mkdir )
    {
        PHPFS_CHECK_RESPONSE_STATUS;
        PHPFS_CLEANUP;
        return 0;
    }
}
