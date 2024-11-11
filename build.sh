#!/bin/bash

# Nom du répertoire à compresser
DIR="injellikrecommendations"

# Vérifier si le répertoire existe
if [ -d "$DIR" ]; then
    # Créer l'archive zip avec le répertoire
    zip -r "${DIR}.zip" "$DIR"
    echo "Le répertoire $DIR a été compressé dans ${DIR}.zip avec succès."
else
    echo "Erreur : Le répertoire $DIR n'existe pas."
    exit 1
fi

