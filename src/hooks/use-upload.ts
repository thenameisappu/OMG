import { useState, useCallback } from 'react'
import axios from 'axios'
import { useDropzone, type FileRejection, type DropzoneOptions } from 'react-dropzone'
import { toast } from 'sonner'

export type UploadedFile = File & {
    preview: string        // object URL for display
    squarePreview: string  // canvas-generated 1:1 preview data URL
    errors: Array<{ message: string; code: string }>
}

export type UseUploadReturn = {
    files: UploadedFile[]
    setFiles: (files: UploadedFile[]) => void
    onUpload: () => Promise<string[]>
    loading: boolean
    successes: string[]
    errors: Array<{ name: string; message: string }>
    maxFileSize: number
    maxFiles: number
    isSuccess: boolean
    isDragActive: boolean
    isDragReject: boolean
    getRootProps: (props?: any) => any
    getInputProps: (props?: any) => any
    inputRef: React.RefObject<HTMLInputElement>
}

interface UseUploadProps extends DropzoneOptions {
    maxFiles?: number
    maxFileSize?: number // in bytes
}

import { API_BASE_URL } from '@/config';

const UPLOAD_URL = `${API_BASE_URL}/upload.php`;

/**
 * Generate a 1:1 square preview of an image file using the Canvas API.
 * Center-crops the image to a square then draws it onto a canvas,
 * matching exactly what the server will do at 1000×1000px.
 *
 * Returns a data-URL (jpeg) of the cropped square preview.
 */
function generateSquarePreview(file: File, previewSize = 300): Promise<string> {
    return new Promise((resolve) => {
        const objectUrl = URL.createObjectURL(file)
        const img = new Image()
        img.onload = () => {
            const squareSize = Math.min(img.naturalWidth, img.naturalHeight)
            const cropX = (img.naturalWidth - squareSize) / 2
            const cropY = (img.naturalHeight - squareSize) / 2

            const canvas = document.createElement('canvas')
            canvas.width = previewSize
            canvas.height = previewSize
            const ctx = canvas.getContext('2d')!

            // Draw the center-cropped square region scaled to previewSize x previewSize
            ctx.drawImage(
                img,
                cropX, cropY,           // source crop start
                squareSize, squareSize,  // source crop size
                0, 0,                   // destination start
                previewSize, previewSize // destination size
            )

            URL.revokeObjectURL(objectUrl)
            resolve(canvas.toDataURL('image/jpeg', 0.9))
        }
        img.onerror = () => {
            URL.revokeObjectURL(objectUrl)
            resolve(URL.createObjectURL(file)) // fallback to original
        }
        img.src = objectUrl
    })
}

export const useUpload = ({
    maxFiles = 1,
    maxFileSize = 5 * 1024 * 1024, // 5MB
    ...dropzoneOptions
}: UseUploadProps = {}) => {
    const [files, setFiles] = useState<UploadedFile[]>([])
    const [loading, setLoading] = useState(false)
    const [successes, setSuccesses] = useState<string[]>([])
    const [errors, setErrors] = useState<Array<{ name: string; message: string }>>([])
    const [isSuccess, setIsSuccess] = useState(false)

    const onDrop = useCallback(async (acceptedFiles: File[], fileRejections: FileRejection[]) => {
        // For accepted files: generate a 1:1 square preview via canvas
        const newFiles: UploadedFile[] = await Promise.all(
            acceptedFiles.map(async (file) => {
                const squarePreview = file.type.startsWith('image/')
                    ? await generateSquarePreview(file)
                    : ''
                return Object.assign(file, {
                    preview: URL.createObjectURL(file),
                    squarePreview,
                    errors: [],
                }) as UploadedFile
            })
        )

        const rejectedFiles = fileRejections.map(({ file, errors }) =>
            Object.assign(file, {
                preview: URL.createObjectURL(file),
                squarePreview: '',
                errors: errors.map((e) => ({ message: e.message, code: e.code })),
            })
        ) as UploadedFile[]

        setFiles((prev) => [...prev, ...newFiles, ...rejectedFiles].slice(0, maxFiles))
        setIsSuccess(false)
        setErrors([])
        setSuccesses([])
    }, [maxFiles])

    const { getRootProps, getInputProps, isDragActive, isDragReject, inputRef } = useDropzone({
        onDrop,
        maxFiles,
        maxSize: maxFileSize,
        accept: {
            'image/*': ['.png', '.jpg', '.jpeg', '.gif', '.webp'],
        },
        ...dropzoneOptions,
    })

    const onUpload = useCallback(async () => {
        setLoading(true)
        setErrors([])
        setSuccesses([])
        const uploadedUrls: string[] = []

        try {
            // Filter out files with errors
            const validFiles = files.filter(f => f.errors.length === 0)

            if (validFiles.length === 0) {
                toast.error("No valid files to upload")
                setLoading(false)
                return []
            }

            for (const file of validFiles) {
                const formData = new FormData()
                formData.append('file', file)

                try {
                    const response = await axios.post(UPLOAD_URL, formData, {
                        headers: {
                            'Content-Type': 'multipart/form-data',
                        },
                        withCredentials: true,
                    })

                    if (response.data.success) {
                        uploadedUrls.push(response.data.url)
                        setSuccesses(prev => [...prev, file.name])
                    } else {
                        setErrors(prev => [...prev, { name: file.name, message: response.data.message || 'Upload failed' }])
                    }
                } catch (err: any) {
                    setErrors(prev => [...prev, { name: file.name, message: err.message || 'Network error' }])
                }
            }

            if (uploadedUrls.length === validFiles.length) {
                setIsSuccess(true)
                toast.success("All files uploaded successfully (1:1 ratio applied on server)")
            } else if (uploadedUrls.length > 0) {
                toast.warning("Some files failed to upload")
            }

        } catch (error) {
            console.error("Upload error:", error)
            toast.error("An unexpected error occurred")
        }
        setLoading(false)
        return uploadedUrls
    }, [files])

    return {
        files,
        setFiles,
        onUpload,
        loading,
        successes,
        errors,
        maxFileSize,
        maxFiles,
        isSuccess,
        getRootProps,
        getInputProps,
        isDragActive,
        isDragReject,
        inputRef
    }
}
